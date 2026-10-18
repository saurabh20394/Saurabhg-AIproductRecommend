<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\UnitInterface;
use Saurabhg\AIproductRecommend\Api\UnitRepositoryInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Ab\ExperimentResolver;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Data\RecommendationItemFactory;
use Saurabhg\AIproductRecommend\Model\Data\RecommendationResult;
use Saurabhg\AIproductRecommend\Model\Data\RecommendationResultFactory;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Provider\CircuitBreaker;
use Saurabhg\AIproductRecommend\Model\Provider\ProviderPool;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;
use Saurabhg\AIproductRecommend\Model\Rule\CampaignProvider;
use Saurabhg\AIproductRecommend\Model\Rule\RuleProcessor;
use Saurabhg\AIproductRecommend\Model\Source\FallbackType;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;
use Saurabhg\AIproductRecommend\Model\Recommendation\Strategy\Complementary;
use Saurabhg\AIproductRecommend\Model\Recommendation\Strategy\StrategyPool;
use Saurabhg\AIproductRecommend\Model\Unit;

/**
 * Recommendation pipeline:
 *   cache -> (remote AI | local strategy) -> Magento eligibility -> exclusions -> rules / campaigns
 *   -> hybrid ranking -> min score / diversity -> pins -> unit fallback.
 * Any failure degrades to the last-good stored result, then to deterministic popular products, and
 * never propagates to the storefront.
 */
class RecommendationService
{
    public const DEPENDENCY_RANKING = 'ranking';

    private const CONTEXT_FREE_EXTRA = 8;

    /**
     * @param Config $config
     * @param UnitRepositoryInterface $unitRepository
     * @param StrategyPool $strategyPool
     * @param ProductDataLoader $productDataLoader
     * @param EligibilityFilter $eligibilityFilter
     * @param RuleProcessor $ruleProcessor
     * @param CampaignProvider $campaignProvider
     * @param Ranker $ranker
     * @param ProductStat $productStat
     * @param Catalog $catalog
     * @param CacheManager $cacheManager
     * @param ExperimentResolver $experimentResolver
     * @param ProviderPool $providerPool
     * @param CircuitBreaker $circuitBreaker
     * @param ReasonResolver $reasonResolver
     * @param TrackingToken $trackingToken
     * @param CandidateSetFactory $candidateSetFactory
     * @param RecommendationResultFactory $resultFactory
     * @param RecommendationItemFactory $itemFactory
     * @param StoreManagerInterface $storeManager
     * @param DeploymentConfig $deploymentConfig
     * @param Logger $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly UnitRepositoryInterface $unitRepository,
        private readonly StrategyPool $strategyPool,
        private readonly ProductDataLoader $productDataLoader,
        private readonly EligibilityFilter $eligibilityFilter,
        private readonly RuleProcessor $ruleProcessor,
        private readonly CampaignProvider $campaignProvider,
        private readonly Ranker $ranker,
        private readonly ProductStat $productStat,
        private readonly Catalog $catalog,
        private readonly CacheManager $cacheManager,
        private readonly ExperimentResolver $experimentResolver,
        private readonly ProviderPool $providerPool,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly ReasonResolver $reasonResolver,
        private readonly TrackingToken $trackingToken,
        private readonly CandidateSetFactory $candidateSetFactory,
        private readonly RecommendationResultFactory $resultFactory,
        private readonly RecommendationItemFactory $itemFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param Context $context
     * @return RecommendationResult
     */
    public function recommend(Context $context): RecommendationResult
    {
        $requestId = bin2hex(random_bytes(8));
        if (!$this->config->isEnabled($context->storeId)) {
            return $this->emptyResult($context, null, $context->type ?? UnitType::POPULAR, 'disabled', $requestId);
        }

        $unit = null;
        if ($context->unitCode !== null) {
            try {
                /** @var Unit $unit */
                $unit = $this->unitRepository->getByCode($context->unitCode);
            } catch (NoSuchEntityException $e) {
                return $this->emptyResult($context, null, UnitType::POPULAR, 'not_found', $requestId);
            }
            if (!$unit->isApplicable($context->storeId, $context->customerGroupId, $context->segmentIds)) {
                return $this->emptyResult($context, $unit, $unit->getType(), 'not_applicable', $requestId);
            }
            $this->experimentResolver->resolve($context, $unit->getCode());
        }

        $type = (string)($context->variantConfig['type'] ?? ($unit ? $unit->getType() : ($context->type ?? UnitType::POPULAR)));
        if (!$this->strategyPool->has($type)) {
            $type = UnitType::POPULAR;
        }
        if (!empty($context->variantConfig['disabled'])) {
            return $this->emptyResult($context, $unit, $type, 'holdout', $requestId);
        }
        $limit = (int)($context->limit ?: ($context->variantConfig['product_count'] ?? ($unit ? $unit->getProductCount() : 6)));
        $limit = max(1, min(50, $limit));

        $hash = $this->cacheManager->getHash($context, $type, $unit ? $unit->getCode() : null, $limit);
        if ($context->useCache && !$context->debug) {
            $cached = $this->cacheManager->load($hash);
            if ($cached !== null) {
                $cached['source_detail'] = $cached['source'] ?? 'local';
                $cached['source'] = 'cache';
                return $this->toResult($cached, $context, $unit, $limit, $requestId);
            }
        }

        try {
            $payload = $this->generate($context, $unit, $type, $limit);
            if (!empty($payload['items']) && !$context->debug) {
                $contextIds = $context->getContextProductIds();
                $this->cacheManager->save(
                    $hash,
                    $payload,
                    array_merge(array_column($payload['items'], 'product_id'), $contextIds),
                    $this->cacheManager->isPersonal($type, $context)
                );
                $this->cacheManager->saveLastGood($hash, $payload, $context->storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Recommendation generation failed, using fallback', [
                'request_id' => $requestId, 'unit' => $context->unitCode, 'error' => $e,
            ]);
            $payload = $this->cacheManager->loadLastGood($hash);
            if ($payload !== null) {
                $payload['source'] = 'last_good';
            } else {
                $payload = $this->deterministicFallback($context, $unit, $limit);
            }
            $payload['is_fallback'] = true;
        }
        return $this->toResult($payload, $context, $unit, $limit, $requestId);
    }

    /**
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param string $type
     * @param int $limit
     * @return array cacheable payload
     */
    private function generate(Context $context, ?UnitInterface $unit, string $type, int $limit): array
    {
        $debug = ['type' => $type];
        $fetchLimit = $limit + (in_array($type, UnitType::CONTEXT_FREE_TYPES, true) ? self::CONTEXT_FREE_EXTRA : 0);
        $source = 'local';
        $modelVersion = null;
        $isFallback = false;
        $remote = false;
        $set = null;

        if ($this->config->isRemoteRankingEnabled($context->storeId)) {
            if ($this->circuitBreaker->isOpen(self::DEPENDENCY_RANKING)) {
                $isFallback = true;
                $debug['remote'] = 'circuit open';
            } else {
                try {
                    $response = $this->providerPool->getRankingProvider()
                        ->recommend($this->buildRemoteRequest($context, $unit, $type, $fetchLimit), $context->storeId);
                    $this->circuitBreaker->recordSuccess(self::DEPENDENCY_RANKING);
                    $set = $this->fromRemote($response['recommendations']);
                    $remote = $set->count() > 0;
                    $source = $remote ? 'remote' : 'local';
                    $modelVersion = $response['model_version'] ?: null;
                    $debug['remote'] = sprintf('%d items from %s', $set->count(), $response['source']);
                } catch (\Throwable $e) {
                    $this->circuitBreaker->recordFailure(self::DEPENDENCY_RANKING, $e->getMessage());
                    $this->logger->warning('Remote recommendation API failed, local engine answers', ['error' => $e]);
                    $isFallback = true;
                    $debug['remote'] = 'error: ' . Logger::sanitize($e->getMessage());
                }
            }
        }
        if (!$remote) {
            $set = $this->collect($type, $context, $unit, $fetchLimit);
        }
        $items = $this->rankAndSelect($set, $context, $unit, $type, $fetchLimit, $remote, $debug);

        if (!$items && $unit && $unit->getFallbackType() !== FallbackType::NONE && $unit->getFallbackType() !== $type) {
            $fallbackType = $unit->getFallbackType();
            $debug['fallback'] = $fallbackType;
            $set = $this->collect($fallbackType, $context, $unit, $fetchLimit + self::CONTEXT_FREE_EXTRA);
            $items = $this->rankAndSelect($set, $context, $unit, $fallbackType, $fetchLimit, false, $debug);
            $source = 'fallback';
            $isFallback = true;
        }

        return [
            'unit_code' => $unit ? $unit->getCode() : null,
            'type' => $type,
            'source' => $source,
            'model_version' => $modelVersion,
            'is_fallback' => $isFallback,
            'items' => $items,
            'debug' => $context->debug ? $debug : null,
        ];
    }

    /**
     * @param string $type
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param int $limit
     * @return CandidateSet
     */
    private function collect(string $type, Context $context, ?UnitInterface $unit, int $limit): CandidateSet
    {
        $strategy = $this->strategyPool->get($type);
        if ($strategy instanceof Complementary) {
            $strategy->setMap($unit ? $unit->getComplementaryMap() : []);
        }
        return $strategy->collect($context, $limit);
    }

    /**
     * @param CandidateSet $set
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param string $type
     * @param int $limit
     * @param bool $remote
     * @param array $debug
     * @return array<int, array> ordered items
     */
    private function rankAndSelect(
        CandidateSet $set,
        Context $context,
        ?UnitInterface $unit,
        string $type,
        int $limit,
        bool $remote,
        array &$debug
    ): array {
        $unitCode = $unit ? $unit->getCode() : null;
        $set->remove($context->getContextProductIds());

        $rules = $this->ruleProcessor->getRules($context, $unitCode);
        $pins = $this->ruleProcessor->getPins($rules) + ($unit ? $unit->getPinnedSkus() : []);
        $pinIds = [];
        foreach ($this->catalog->getIdsBySkus(array_keys($pins)) as $sku => $productId) {
            foreach ($pins as $pinSku => $slot) {
                if (strcasecmp((string)$pinSku, (string)$sku) === 0 && !in_array($productId, $context->getContextProductIds(), true)) {
                    $pinIds[$productId] = $slot;
                    $set->touch($productId);
                }
            }
        }

        $candidateIds = $set->getIds();
        $facts = $this->productDataLoader->load($candidateIds, $context, $this->ruleProcessor->getRequiredAttributes($rules));
        $excluded = array_fill_keys(array_diff($candidateIds, array_keys($facts)), 'disabled, not visible or not in website');

        $filtered = $this->eligibilityFilter->filter($facts, $context, $unit);
        $excluded += $filtered['excluded'];
        $eligible = $filtered['eligible'];

        $ruleOutcome = $this->ruleProcessor->apply($rules, $eligible);
        $excluded += $ruleOutcome['excluded'];
        $eligible = array_diff_key($eligible, $ruleOutcome['excluded']);

        $campaigns = $this->campaignProvider->getBoosts($context, $unitCode, $eligible);
        $maxBoost = abs($this->config->getFloat('ranking/max_boost', $context->storeId, 1.0));
        $boosts = [];
        foreach (array_keys($eligible) as $productId) {
            $boost = ($ruleOutcome['boosts'][$productId] ?? 0.0) + ($campaigns['boosts'][$productId] ?? 0.0);
            if ($boost != 0.0) {
                $boosts[$productId] = max(-$maxBoost, min($maxBoost, $boost));
            }
        }

        $set->normalize('behavior');
        $weights = $this->ranker->getWeights(
            $type,
            $this->config->getWeights($context->storeId),
            (array)($context->variantConfig['weights'] ?? []),
            $remote
        );
        $stats = $this->productStat->getScores($context->storeId, array_keys($eligible));
        $ranked = $this->ranker->rank($set, $eligible, $stats, $context->profile, $weights, $boosts);

        $minScore = $unit ? $unit->getMinScore() : 0.0;
        $maxPerCategory = $this->config->getInt('ranking/max_per_category', $context->storeId);
        $selected = [];
        $categoryCounts = [];
        $suppressed = [];
        foreach ($ranked as $productId => $row) {
            if (isset($pinIds[$productId])) {
                continue;
            }
            if ($row['final'] < $minScore || $row['final'] <= 0) {
                $suppressed[$productId] = $row['final'];
                continue;
            }
            if ($maxPerCategory > 0) {
                $primary = $eligible[$productId]['categories'][0] ?? 0;
                if (($categoryCounts[$primary] ?? 0) >= $maxPerCategory) {
                    continue;
                }
                $categoryCounts[$primary] = ($categoryCounts[$primary] ?? 0) + 1;
            }
            $selected[] = $productId;
            if (count($selected) >= $limit) {
                break;
            }
        }

        // pinned products take their slots (exclusions still apply: excluded pins are never shown)
        $slots = [];
        asort($pinIds);
        foreach ($pinIds as $productId => $slot) {
            if (!isset($eligible[$productId])) {
                continue;
            }
            $slot = max(1, $slot);
            while (isset($slots[$slot])) {
                $slot++;
            }
            if ($slot <= $limit) {
                $slots[$slot] = $productId;
            }
        }
        $ordered = [];
        $queue = $selected;
        for ($position = 1; $position <= $limit; $position++) {
            if (isset($slots[$position])) {
                $ordered[] = $slots[$position];
            } elseif ($queue) {
                $ordered[] = array_shift($queue);
            }
        }

        $items = [];
        foreach ($ordered as $index => $productId) {
            $row = $ranked[$productId] ?? ['final' => 1.0, 'features' => [], 'reason' => 'featured', 'boost' => 0.0];
            $item = [
                'product_id' => $productId,
                'sku' => $eligible[$productId]['sku'],
                'score' => round(max(0.0, min(1.0, $row['final'])), 4),
                'reason_code' => isset($pinIds[$productId]) ? 'featured' : $this->reasonResolver->normalize($row['reason']),
                'position' => $index + 1,
            ];
            if ($context->debug) {
                $item['features'] = $row['features'];
                $item['boost'] = $row['boost'];
                $item['final'] = $row['final'];
                $item['pinned'] = isset($pinIds[$productId]);
            }
            $items[] = $item;
        }

        if ($context->debug) {
            $debug['weights'] = $weights;
            $debug['candidates'] = count($candidateIds);
            $debug['eligible'] = count($eligible);
            $debug['rules'] = $ruleOutcome['applied'];
            $debug['campaigns'] = $campaigns['applied'];
            $debug['pins'] = $pinIds;
            $debug['excluded'] = ($debug['excluded'] ?? []) + array_slice($excluded, 0, 100, true);
            $debug['suppressed'] = array_slice($suppressed, 0, 50, true);
        }
        return $items;
    }

    /**
     * Deterministic Magento fallback: popular (or newest) eligible products, no rules / AI
     *
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param int $limit
     * @return array
     */
    private function deterministicFallback(Context $context, ?UnitInterface $unit, int $limit): array
    {
        $items = [];
        try {
            $scores = $this->productStat->getTop($context->storeId, 'popularity_score', $limit * 4)
                ?: $this->catalog->getNewest($context->websiteId, $limit * 4);
            $scores = array_diff_key($scores, array_flip($context->getContextProductIds()));
            $facts = $this->eligibilityFilter->filter(
                $this->productDataLoader->load(array_keys($scores), $context),
                $context,
                $unit
            )['eligible'];
            foreach (array_keys($scores) as $productId) {
                if (isset($facts[$productId])) {
                    $items[] = [
                        'product_id' => $productId, 'sku' => $facts[$productId]['sku'], 'score' => 0.5,
                        'reason_code' => 'popular', 'position' => count($items) + 1,
                    ];
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Deterministic fallback failed', ['error' => $e]);
        }
        return [
            'unit_code' => $unit ? $unit->getCode() : null,
            'type' => UnitType::POPULAR,
            'source' => 'fallback',
            'model_version' => null,
            'is_fallback' => true,
            'items' => $items,
        ];
    }

    /**
     * Remote request (requirements §21); customer IDs are pseudonymized, no PII is sent
     *
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param string $type
     * @param int $limit
     * @return array
     */
    private function buildRemoteRequest(Context $context, ?UnitInterface $unit, string $type, int $limit): array
    {
        $recent = array_slice(array_map('intval', $context->profile['recent'] ?? []), 0, 10);
        $skus = $this->catalog->getSkus($recent);
        return [
            'store_id' => $this->storeManager->getStore($context->storeId)->getCode(),
            'visitor_id' => $context->visitorId,
            'customer_id' => $context->customerId ? $this->pseudonymize('c' . $context->customerId) : null,
            'context' => [
                'page_type' => $context->pageType,
                'product_sku' => $context->productSku,
                'category_id' => $context->categoryId,
                'cart_skus' => $context->cartSkus,
                'customer_group_id' => $context->customerGroupId,
            ],
            'profile' => [
                'top_categories' => array_map('intval', ProfileService::top($context->profile, 'cat', 5)),
                'top_brands' => ProfileService::top($context->profile, 'brand', 3),
                'recent_skus' => array_values(array_intersect_key($skus, array_flip($recent))),
            ],
            'unit' => [
                'code' => $unit ? $unit->getCode() : $type,
                'type' => $type,
                'limit' => min(100, $limit * 3),
            ],
        ];
    }

    /**
     * @param array $recommendations validated remote items
     * @return CandidateSet
     */
    private function fromRemote(array $recommendations): CandidateSet
    {
        $set = $this->candidateSetFactory->create();
        $ids = $this->catalog->getIdsBySkus(array_column($recommendations, 'sku'));
        $lower = array_change_key_case($ids, CASE_LOWER);
        foreach ($recommendations as $item) {
            $productId = $lower[strtolower($item['sku'])] ?? null;
            if ($productId) {
                $set->add((int)$productId, 'ai', max(0.0001, (float)$item['score']), $item['reason_code'] ?: 'recommended');
            }
        }
        return $set;
    }

    /**
     * @param array $payload
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param int $limit
     * @param string $requestId
     * @return RecommendationResult
     */
    private function toResult(array $payload, Context $context, ?UnitInterface $unit, int $limit, string $requestId): RecommendationResult
    {
        $exclude = array_flip($context->getContextProductIds());
        $exposeScore = $context->debug || $this->config->isSetFlag('storefront/expose_score', $context->storeId);
        $items = [];
        foreach ((array)($payload['items'] ?? []) as $row) {
            if (isset($exclude[(int)$row['product_id']]) || count($items) >= $limit) {
                continue;
            }
            $code = $this->reasonResolver->normalize((string)$row['reason_code']);
            $data = $row;
            $data['position'] = count($items) + 1;
            $data['reason_code'] = $code;
            $data['reason_label'] = $this->reasonResolver->getLabel($code);
            $data['score'] = $exposeScore ? (float)$row['score'] : null;
            $items[] = $this->itemFactory->create(['data' => $data]);
        }
        $unitCode = $unit ? $unit->getCode() : ($payload['unit_code'] ?? null);
        $type = (string)($payload['type'] ?? UnitType::POPULAR);
        return $this->resultFactory->create(['data' => [
            'unit_code' => $unitCode,
            'title' => $unit ? $unit->getTitle() : null,
            'type' => $type,
            'source' => (string)($payload['source'] ?? 'local'),
            'source_detail' => $payload['source_detail'] ?? null,
            'model_version' => $payload['model_version'] ?? null,
            'is_fallback' => (bool)($payload['is_fallback'] ?? false),
            'request_id' => $requestId,
            'tracking_token' => $this->trackingToken->encode(
                $unitCode ?? ('type:' . $type),
                $requestId,
                $context->storeId,
                $context->experimentId,
                $context->variantId
            ),
            'experiment_id' => $context->experimentId,
            'variant_id' => $context->variantId,
            'variant_code' => $context->variantCode,
            'show_reason' => $unit ? $unit->isShowReason() : true,
            'items' => $items,
            'debug' => $payload['debug'] ?? null,
        ]]);
    }

    /**
     * @param Context $context
     * @param UnitInterface|null $unit
     * @param string $type
     * @param string $source
     * @param string $requestId
     * @return RecommendationResult
     */
    private function emptyResult(Context $context, ?UnitInterface $unit, string $type, string $source, string $requestId): RecommendationResult
    {
        return $this->toResult(
            ['unit_code' => $unit ? $unit->getCode() : $context->unitCode, 'type' => $type, 'source' => $source, 'items' => []],
            $context,
            $unit,
            1,
            $requestId
        );
    }

    /**
     * @param string $value
     * @return string
     */
    private function pseudonymize(string $value): string
    {
        $key = (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);
        return 'p_' . substr(hash_hmac('sha256', $value, 'aipr-id|' . $key), 0, 24);
    }
}
