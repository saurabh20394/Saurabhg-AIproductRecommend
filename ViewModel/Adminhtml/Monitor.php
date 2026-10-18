<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\ViewModel\Adminhtml;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface;
use Saurabhg\AIproductRecommend\Api\HealthCheckInterface;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Embedding index progress, scheduled jobs, failures, dependency health and operational actions
 */
class Monitor implements ArgumentInterface
{
    private const JOB_LABELS = [
        'saurabhg_airecommend_trending_calculation' => 'Popularity & trending',
        'saurabhg_airecommend_co_purchase_calculation' => 'Bought together & also viewed',
        'saurabhg_airecommend_embedding_generation' => 'Embeddings (pending products)',
        'saurabhg_airecommend_catalog_sync' => 'Catalog reconciliation',
        'saurabhg_airecommend_profile_aggregation' => 'Shopper profiles',
        'saurabhg_airecommend_recommendation_precompute' => 'Cache warm-up',
        'saurabhg_airecommend_analytics_aggregation' => 'Report aggregation',
        'saurabhg_airecommend_event_cleanup' => 'Data retention cleanup',
    ];

    private ?array $health = null;

    /**
     * @param ProductIndex $productIndex
     * @param Relation $relation
     * @param StoreResolver $storeResolver
     * @param StoreManagerInterface $storeManager
     * @param HealthCheckInterface $healthCheck
     * @param AuthorizationInterface $authorization
     * @param UrlInterface $urlBuilder
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ProductIndex $productIndex,
        private readonly Relation $relation,
        private readonly StoreResolver $storeResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly HealthCheckInterface $healthCheck,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $urlBuilder,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array[] per store: name, totals by status, percent, relation coverage
     */
    public function getStoreStatus(): array
    {
        $summary = $this->productIndex->getStatusSummary();
        $enabled = $this->storeResolver->getStoreIds();
        $rows = [];
        foreach ($this->storeResolver->getStoreIds(true) as $storeId) {
            $counts = $summary[$storeId] ?? [];
            $total = array_sum($counts);
            $done = ($counts[ProductIndex::STATUS_INDEXED] ?? 0) + ($counts[ProductIndex::STATUS_SKIPPED] ?? 0);
            $store = $this->storeManager->getStore($storeId);
            $rows[] = [
                'store' => $store->getWebsite()->getName() . ' / ' . $store->getName(),
                'enabled' => in_array($storeId, $enabled, true),
                'total' => $total,
                'indexed' => $counts[ProductIndex::STATUS_INDEXED] ?? 0,
                'skipped' => $counts[ProductIndex::STATUS_SKIPPED] ?? 0,
                'pending' => $counts[ProductIndex::STATUS_PENDING] ?? 0,
                'failed' => $counts[ProductIndex::STATUS_FAILED] ?? 0,
                'percent' => $total ? round($done / $total * 100, 1) : 0,
                'similar' => $this->relation->countRelations($storeId, Relation::SIMILAR),
                'co_purchase' => $this->relation->countRelations($storeId, Relation::CO_PURCHASE),
                'co_view' => $this->relation->countRelations($storeId, Relation::CO_VIEW),
            ];
        }
        return $rows;
    }

    /**
     * @return array
     */
    public function getFailures(): array
    {
        return $this->productIndex->getFailures(30);
    }

    /**
     * @return HealthComponentInterface[]
     */
    public function getHealth(): array
    {
        if ($this->health === null) {
            $this->health = $this->healthCheck->check();
        }
        return $this->health;
    }

    /**
     * Worst status across dependencies
     *
     * @return string
     */
    public function getOverallHealth(): string
    {
        $statuses = array_map(static fn($component) => $component->getStatus(), $this->getHealth());
        foreach ([HealthComponentInterface::STATUS_DOWN, HealthComponentInterface::STATUS_WARNING] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }
        return HealthComponentInterface::STATUS_OK;
    }

    /**
     * Last run and next schedule of every module cron job
     *
     * @return array[] {code, label, status, finished_at, scheduled_at, message}
     */
    public function getJobs(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('cron_schedule');
        $last = $next = [];
        try {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['job_code', 'status', 'messages', 'scheduled_at', 'finished_at'])
                    ->where('job_code IN (?)', array_keys(self::JOB_LABELS))
                    ->where('status IN (?)', ['success', 'error', 'missed', 'running'])
                    ->order('scheduled_at DESC')
                    ->limit(500)
            );
            foreach ($rows as $row) {
                $last[$row['job_code']] ??= $row;
            }
            foreach ($connection->fetchAll(
                $connection->select()
                    ->from($table, ['job_code', 'scheduled_at' => new \Zend_Db_Expr('MIN(scheduled_at)')])
                    ->where('job_code IN (?)', array_keys(self::JOB_LABELS))
                    ->where('status = ?', 'pending')
                    ->group('job_code')
            ) as $row) {
                $next[$row['job_code']] = $row['scheduled_at'];
            }
        } catch (\Throwable $e) {
            unset($e);
        }
        $jobs = [];
        foreach (self::JOB_LABELS as $code => $label) {
            $run = $last[$code] ?? null;
            $jobs[] = [
                'code' => $code,
                'label' => __($label),
                'status' => $run['status'] ?? 'never',
                'finished_at' => $run['finished_at'] ?? null,
                'next' => $next[$code] ?? null,
                'message' => $run && $run['status'] === 'error' ? mb_substr((string)$run['messages'], 0, 200) : '',
            ];
        }
        return $jobs;
    }

    /**
     * Operational actions with a plain-language description
     *
     * @return array[] {code, label, description, primary}
     */
    public function getActions(): array
    {
        return [
            [
                'code' => 'process_pending',
                'label' => __('Process Pending Now'),
                'description' => __('Embed queued products right away instead of waiting for cron (up to 500).'),
                'primary' => true,
            ],
            [
                'code' => 'calculate',
                'label' => __('Recalculate Signals'),
                'description' => __('Rebuild popularity, trending, bought-together, also-viewed and report data.'),
                'primary' => false,
            ],
            [
                'code' => 'retry_failed',
                'label' => __('Retry Failed Products'),
                'description' => __('Put products whose embedding failed back in the queue.'),
                'primary' => false,
            ],
            [
                'code' => 'reindex',
                'label' => __('Queue Full Reindex'),
                'description' => __('Mark every product for re-checking. Unchanged products are skipped quickly.'),
                'primary' => false,
            ],
        ];
    }

    /**
     * @return bool
     */
    public function canRunActions(): bool
    {
        return $this->authorization->isAllowed('Saurabhg_AIproductRecommend::reindex');
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
