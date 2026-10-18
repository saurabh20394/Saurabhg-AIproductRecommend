<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Cron;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\App\Emulation;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Analytics\Aggregator;
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\Catalog\ProductIndexer;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Privacy\RetentionCleaner;
use Saurabhg\AIproductRecommend\Model\Recommendation\ContextBuilder;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Profile as ProfileResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit\CollectionFactory as UnitCollectionFactory;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;
use Saurabhg\AIproductRecommend\Model\Stats\RelationCalculator;
use Saurabhg\AIproductRecommend\Model\Stats\StatsCalculator;
use Saurabhg\AIproductRecommend\Model\StoreResolver;
use Saurabhg\AIproductRecommend\Model\Unit;

/**
 * Scheduled jobs (requirements §38); every job is idempotent and isolated per store view
 */
class Jobs
{
    /**
     * @param StoreResolver $storeResolver
     * @param StatsCalculator $statsCalculator
     * @param RelationCalculator $relationCalculator
     * @param Aggregator $aggregator
     * @param RetentionCleaner $retentionCleaner
     * @param ProductIndexer $productIndexer
     * @param IndexQueue $indexQueue
     * @param Catalog $catalog
     * @param ProfileService $profileService
     * @param ResourceConnection $resourceConnection
     * @param UnitCollectionFactory $unitCollectionFactory
     * @param ContextBuilder $contextBuilder
     * @param RecommendationService $recommendationService
     * @param Emulation $emulation
     * @param ErrorLog $errorLog
     * @param Logger $logger
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly StatsCalculator $statsCalculator,
        private readonly RelationCalculator $relationCalculator,
        private readonly Aggregator $aggregator,
        private readonly RetentionCleaner $retentionCleaner,
        private readonly ProductIndexer $productIndexer,
        private readonly IndexQueue $indexQueue,
        private readonly Catalog $catalog,
        private readonly ProfileService $profileService,
        private readonly ResourceConnection $resourceConnection,
        private readonly UnitCollectionFactory $unitCollectionFactory,
        private readonly ContextBuilder $contextBuilder,
        private readonly RecommendationService $recommendationService,
        private readonly Emulation $emulation,
        private readonly ErrorLog $errorLog,
        private readonly Logger $logger
    ) {
    }

    /**
     * trending_calculation (hourly): popularity, trending and conversion scores
     *
     * @return array<int, int>
     */
    public function calculateStats(): array
    {
        $result = [];
        foreach ($this->storeResolver->getStoreIds() as $storeId) {
            $result[$storeId] = $this->guard('stats', fn() => $this->statsCalculator->calculate($storeId), $storeId) ?? 0;
        }
        return $result;
    }

    /**
     * co_purchase_calculation (daily): frequently bought together and also viewed relations
     *
     * @return array<int, array{co_purchase: int, co_view: int}>
     */
    public function calculateRelations(): array
    {
        $result = [];
        foreach ($this->storeResolver->getStoreIds() as $storeId) {
            $result[$storeId] = [
                'co_purchase' => $this->guard('co_purchase', fn() => $this->relationCalculator->calculateCoPurchase($storeId), $storeId) ?? 0,
                'co_view' => $this->guard('co_view', fn() => $this->relationCalculator->calculateCoView($storeId), $storeId) ?? 0,
            ];
        }
        return $result;
    }

    /**
     * analytics_aggregation (hourly): yesterday and today
     *
     * @return int
     */
    public function aggregateAnalytics(): int
    {
        return (int)$this->guard('analytics', fn() => $this->aggregator->aggregate(gmdate('Y-m-d', time() - 86400), gmdate('Y-m-d')));
    }

    /**
     * event_cleanup (daily): retention policy
     *
     * @return array
     */
    public function cleanup(): array
    {
        return (array)$this->guard('cleanup', fn() => $this->retentionCleaner->clean());
    }

    /**
     * embedding_generation fallback (every 10 minutes): index pending products even without queue consumers
     *
     * @return array
     */
    public function processPendingIndex(): array
    {
        return (array)$this->guard('index_pending', fn() => $this->productIndexer->reindexPending(500));
    }

    /**
     * catalog_sync scheduled reconciliation (daily): re-queue products changed in the last 25 hours
     *
     * @return int
     */
    public function reconcileCatalog(): int
    {
        return (int)$this->guard('catalog_reconcile', function () {
            $ids = $this->catalog->getUpdatedSince(gmdate('Y-m-d H:i:s', time() - 25 * 3600), 50000);
            return $this->indexQueue->enqueue($ids);
        });
    }

    /**
     * profile_aggregation (hourly): apply decay / pruning to recently active profiles
     *
     * @return int
     */
    public function aggregateProfiles(): int
    {
        return (int)$this->guard('profiles', function () {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(ProfileResource::TABLE);
            $rows = $connection->fetchAll($connection->select()->from($table, ['profile_id', 'features_json'])
                ->where('updated_at >= ?', gmdate('Y-m-d H:i:s', time() - 7200))
                ->where('merged_into IS NULL')
                ->limit(5000));
            foreach ($rows as $row) {
                $features = json_decode((string)$row['features_json'], true);
                if (is_array($features)) {
                    $connection->update(
                        $table,
                        ['features_json' => json_encode($this->profileService->decay($features))],
                        ['profile_id = ?' => (int)$row['profile_id']]
                    );
                }
            }
            return count($rows);
        });
    }

    /**
     * recommendation_precompute (hourly): warm the cache of context-free placement units
     *
     * @return int warmed units
     */
    public function precompute(): int
    {
        $warmed = 0;
        $units = $this->unitCollectionFactory->create()
            ->addFieldToFilter('status', 1)
            ->addFieldToFilter('type', ['in' => UnitType::CONTEXT_FREE_TYPES]);
        foreach ($this->storeResolver->getStoreIds() as $storeId) {
            /** @var Unit $unit */
            foreach ($units as $unit) {
                if (!$unit->isForStore($storeId) || $unit->getCustomerGroupIds() || $unit->getSegmentIds()) {
                    continue;
                }
                $warmed += (int)$this->guard('precompute', function () use ($unit, $storeId) {
                    $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                    try {
                        $context = $this->contextBuilder->create(
                            ['store_id' => $storeId, 'unit_code' => $unit->getCode(), 'page_type' => 'home'],
                            Context::CHANNEL_WEB
                        );
                        return count($this->recommendationService->recommend($context)->getItems()) > 0 ? 1 : 0;
                    } finally {
                        $this->emulation->stopEnvironmentEmulation();
                    }
                }, $storeId);
            }
        }
        return $warmed;
    }

    /**
     * @param string $job
     * @param callable $callback
     * @param int|null $storeId
     * @return mixed|null
     */
    private function guard(string $job, callable $callback, ?int $storeId = null)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Cron job %s failed', $job), ['store_id' => $storeId, 'error' => $e]);
            $this->errorLog->add(ErrorLog::JOB_STATS, $job . ': ' . $e->getMessage(), ['job' => $job, 'store_id' => $storeId]);
            return null;
        }
    }
}
