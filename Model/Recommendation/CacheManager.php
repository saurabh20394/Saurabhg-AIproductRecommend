<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Framework\App\Cache\StateInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Cache\Type as CacheType;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Result as ResultResource;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Result caching by normalized context hash: Magento cache (Redis when configured) with TTL +
 * tag invalidation, plus a persistent last-good copy for fallback
 */
class CacheManager
{
    /**
     * @param CacheType $cache
     * @param StateInterface $cacheState
     * @param ResultResource $resultResource
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        private readonly CacheType $cache,
        private readonly StateInterface $cacheState,
        private readonly ResultResource $resultResource,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * Normalized context hash; context-free types share one entry per store / group / variant
     *
     * @param Context $context
     * @param string $type
     * @param string|null $unitCode
     * @param int $limit
     * @return string
     */
    public function getHash(Context $context, string $type, ?string $unitCode, int $limit): string
    {
        $parts = [
            'v1', $context->storeId, $unitCode ?? '-', $type, $limit, $context->customerGroupId,
            $context->variantId ?? 0, implode(',', $this->sorted($context->segmentIds)),
        ];
        if (!in_array($type, UnitType::CONTEXT_FREE_TYPES, true)) {
            $parts[] = (int)$context->productId;
            $parts[] = (int)$context->categoryId;
            $parts[] = implode(',', $this->sorted($context->cartProductIds));
            $parts[] = implode(',', $this->sorted($context->orderProductIds));
            $parts[] = $context->pageType;
        }
        if ($this->isPersonal($type, $context)) {
            $parts[] = $context->getSubjectKey() ?? '-';
        }
        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param string $type
     * @param Context $context
     * @return bool
     */
    public function isPersonal(string $type, Context $context): bool
    {
        return in_array($type, UnitType::PERSONAL_TYPES, true) && $context->getSubjectKey() !== null;
    }

    /**
     * @param string $hash
     * @return array|null
     */
    public function load(string $hash): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        try {
            $raw = $this->cache->load('aipr_' . $hash);
        } catch (\Throwable $e) {
            $this->logger->warning('Recommendation cache read failed (degraded): ' . $e->getMessage());
            return null;
        }
        $data = $raw ? json_decode((string)$raw, true) : null;
        return is_array($data) ? $data : null;
    }

    /**
     * @param string $hash
     * @param array $payload
     * @param int[] $productIds products in the result and in the context
     * @param bool $personal
     * @return void
     */
    public function save(string $hash, array $payload, array $productIds, bool $personal): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $ttl = $personal
            ? $this->config->getInt('cache/personalized_ttl', null, 300)
            : $this->config->getInt('cache/ttl', null, 900);
        if ($ttl <= 0) {
            return;
        }
        $tags = [CacheType::TAG_RESULTS];
        foreach (array_unique($productIds) as $productId) {
            $tags[] = CacheType::TAG_PRODUCT_PREFIX . (int)$productId;
        }
        try {
            $this->cache->save((string)json_encode($payload), 'aipr_' . $hash, $tags, $ttl);
        } catch (\Throwable $e) {
            $this->logger->warning('Recommendation cache write failed (degraded): ' . $e->getMessage());
        }
    }

    /**
     * Persist the last good result for fallback
     *
     * @param string $hash
     * @param array $payload
     * @param int $storeId
     * @return void
     */
    public function saveLastGood(string $hash, array $payload, int $storeId): void
    {
        $hours = $this->config->getInt('cache/fallback_hours', null, 24);
        if ($hours <= 0 || empty($payload['items'])) {
            return;
        }
        try {
            $this->resultResource->saveResult(
                $hash,
                $payload['unit_code'] ?? null,
                $storeId,
                $payload,
                array_column($payload['items'], 'product_id'),
                (string)($payload['source'] ?? ''),
                $payload['model_version'] ?? null,
                $hours
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Last-good result could not be stored: ' . $e->getMessage());
        }
    }

    /**
     * @param string $hash
     * @return array|null
     */
    public function loadLastGood(string $hash): ?array
    {
        try {
            return $this->resultResource->loadResult($hash);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Invalidate results containing or generated for products
     *
     * @param int[] $productIds
     * @return void
     */
    public function cleanProducts(array $productIds): void
    {
        $tags = array_map(static fn($id) => CacheType::TAG_PRODUCT_PREFIX . (int)$id, $productIds);
        if (!$tags) {
            return;
        }
        try {
            $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
            $this->resultResource->deleteContaining($productIds);
        } catch (\Throwable $e) {
            $this->logger->warning('Recommendation cache invalidation failed: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate every cached result (merchant configuration changed)
     *
     * @return void
     */
    public function cleanAll(): void
    {
        try {
            $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [CacheType::TAG_RESULTS]);
        } catch (\Throwable $e) {
            $this->logger->warning('Recommendation cache invalidation failed: ' . $e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER);
    }

    /**
     * @param int[] $values
     * @return int[]
     */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);
        return $values;
    }
}
