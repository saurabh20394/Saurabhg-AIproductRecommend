<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Catalog;

use Magento\Framework\App\Area;
use Magento\Store\Model\App\Emulation;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Provider\Http\CatalogSyncClient;
use Saurabhg\AIproductRecommend\Model\Provider\Local\HashingEmbeddingProvider;
use Saurabhg\AIproductRecommend\Model\Provider\ProviderPool;
use Saurabhg\AIproductRecommend\Model\Provider\VectorMath;
use Saurabhg\AIproductRecommend\Model\Recommendation\CacheManager;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Incremental product indexing per store view: embeddings (only when content or model changed),
 * vector neighbors, optional catalog sync to the AI service, and cache invalidation
 */
class ProductIndexer
{
    /**
     * @param ProductPayloadBuilder $payloadBuilder
     * @param ProductIndex $productIndex
     * @param Relation $relation
     * @param VectorSearch $vectorSearch
     * @param ProviderPool $providerPool
     * @param CatalogSyncClient $catalogSyncClient
     * @param CacheManager $cacheManager
     * @param StoreResolver $storeResolver
     * @param Emulation $emulation
     * @param ErrorLog $errorLog
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        private readonly ProductPayloadBuilder $payloadBuilder,
        private readonly ProductIndex $productIndex,
        private readonly Relation $relation,
        private readonly VectorSearch $vectorSearch,
        private readonly ProviderPool $providerPool,
        private readonly CatalogSyncClient $catalogSyncClient,
        private readonly CacheManager $cacheManager,
        private readonly StoreResolver $storeResolver,
        private readonly Emulation $emulation,
        private readonly ErrorLog $errorLog,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int[]|null $storeIds null = all enabled stores
     * @return array{indexed: int, unchanged: int, skipped: int, failed: int, synced: int}
     */
    public function reindex(array $productIds, ?array $storeIds = null): array
    {
        $totals = ['indexed' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'synced' => 0];
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return $totals;
        }
        $batch = max(1, $this->config->getInt('catalog/batch_size', null, 50));
        foreach ($storeIds ?? $this->storeResolver->getStoreIds() as $storeId) {
            $this->emulation->startEnvironmentEmulation((int)$storeId, Area::AREA_FRONTEND, true);
            try {
                foreach (array_chunk($productIds, $batch) as $chunk) {
                    foreach ($this->reindexChunk($chunk, (int)$storeId) as $key => $count) {
                        $totals[$key] += $count;
                    }
                }
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        }
        return $totals;
    }

    /**
     * Process the pending queue (reconciliation / when consumers are not running)
     *
     * @param int $limit
     * @return array
     */
    public function reindexPending(int $limit = 500): array
    {
        $totals = ['indexed' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'synced' => 0];
        $enabled = $this->storeResolver->getStoreIds();
        foreach ($this->productIndex->getPending($limit) as $storeId => $ids) {
            if (!in_array($storeId, $enabled, true)) {
                continue;
            }
            foreach ($this->reindex($ids, [$storeId]) as $key => $count) {
                $totals[$key] += $count;
            }
        }
        return $totals;
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array
     */
    private function reindexChunk(array $productIds, int $storeId): array
    {
        $totals = ['indexed' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'synced' => 0];
        $built = $this->payloadBuilder->build($productIds, $storeId);
        $existing = $this->productIndex->getRows($storeId, $productIds);
        $provider = $this->providerPool->getEmbeddingProvider($storeId);
        $model = $provider->getModel($storeId);
        $version = $provider->getModelVersion($storeId);
        $weighted = $provider instanceof HashingEmbeddingProvider;
        $syncEnabled = $this->config->isCatalogSyncEnabled($storeId);
        $now = gmdate('Y-m-d H:i:s');

        $rows = [];
        $toEmbed = [];
        $toSync = [];
        $removed = array_diff($productIds, array_keys($built));
        foreach ($built as $productId => $data) {
            $old = $existing[$productId] ?? [];
            $payloadHash = hash('sha256', (string)json_encode($data['payload']));
            $syncStatus = $syncEnabled
                ? (($old['payload_hash'] ?? null) === $payloadHash && ($old['sync_status'] ?? '') === 'synced' ? 'synced' : 'pending')
                : 'disabled';
            if ($syncStatus === 'pending') {
                $toSync[$productId] = $data['payload'];
            }
            if (!$data['eligible']) {
                $rows[$productId] = $this->row($storeId, $productId, $data['sku'], [
                    'status' => ProductIndex::STATUS_SKIPPED, 'vector' => null, 'content_hash' => null,
                    'payload_hash' => $payloadHash, 'sync_status' => $syncStatus, 'indexed_at' => $now,
                ]);
                $totals['skipped']++;
                continue;
            }
            $text = $this->payloadBuilder->toText($data['sections'], $weighted);
            $contentHash = hash('sha256', $model . '|' . $version . '|' . $text);
            if (($old['content_hash'] ?? null) === $contentHash && ($old['status'] ?? '') === ProductIndex::STATUS_INDEXED) {
                $rows[$productId] = $this->row($storeId, $productId, $data['sku'], [
                    'status' => ProductIndex::STATUS_INDEXED, 'vector' => $old['vector'], 'content_hash' => $contentHash,
                    'model' => $old['model'], 'model_version' => $old['model_version'], 'dimensions' => (int)$old['dimensions'],
                    'payload_hash' => $payloadHash, 'sync_status' => $syncStatus, 'indexed_at' => $old['indexed_at'] ?? $now,
                ]);
                $totals['unchanged']++;
                continue;
            }
            $toEmbed[$productId] = ['text' => $text, 'hash' => $contentHash, 'sku' => $data['sku'], 'payload_hash' => $payloadHash, 'sync' => $syncStatus];
        }

        $embedded = [];
        if ($toEmbed) {
            try {
                $vectors = $provider->embed(array_map(static fn($item) => $item['text'], $toEmbed), $storeId);
                foreach ($toEmbed as $productId => $item) {
                    $vector = $vectors[$productId] ?? null;
                    if (!$vector) {
                        continue;
                    }
                    $rows[$productId] = $this->row($storeId, $productId, $item['sku'], [
                        'status' => ProductIndex::STATUS_INDEXED, 'vector' => VectorMath::pack($vector),
                        'content_hash' => $item['hash'], 'model' => $model, 'model_version' => $version,
                        'dimensions' => count($vector), 'payload_hash' => $item['payload_hash'],
                        'sync_status' => $item['sync'], 'indexed_at' => $now,
                    ]);
                    $embedded[] = $productId;
                }
                $totals['indexed'] += count($embedded);
            } catch (\Throwable $e) {
                $failed = array_keys($toEmbed);
                // ensure rows exist without resetting the attempt counter
                $this->productIndex->saveRows(array_map(static fn($id) => [
                    'store_id' => $storeId, 'product_id' => $id, 'sku' => mb_substr($toEmbed[$id]['sku'], 0, 64),
                ], $failed));
                $this->productIndex->markFailed($storeId, $failed, Logger::sanitize($e->getMessage()));
                $this->errorLog->add(ErrorLog::JOB_PRODUCT_INDEX, $e->getMessage(), ['store_id' => $storeId, 'product_ids' => $failed]);
                $this->logger->error('Embedding generation failed', ['store_id' => $storeId, 'error' => $e]);
                $totals['failed'] += count($failed);
                foreach ($failed as $productId) {
                    unset($toSync[$productId]);
                }
            }
        }

        if ($toSync) {
            try {
                $this->catalogSyncClient->push($toSync, $storeId);
                foreach (array_keys($toSync) as $productId) {
                    if (isset($rows[$productId])) {
                        $rows[$productId]['sync_status'] = 'synced';
                    }
                }
                $totals['synced'] += count($toSync);
            } catch (\Throwable $e) {
                foreach (array_keys($toSync) as $productId) {
                    if (isset($rows[$productId])) {
                        $rows[$productId]['sync_status'] = 'failed';
                    }
                }
                $this->logger->error('Catalog sync failed', ['store_id' => $storeId, 'error' => $e]);
            }
        }

        $this->productIndex->saveRows(array_values($rows));
        $this->updateNeighbors($storeId, $embedded, array_keys(array_filter($built, static fn($d) => !$d['eligible'])));
        if ($removed) {
            $this->productIndex->deleteProducts($removed);
        }
        $changed = array_merge($embedded, $removed, array_keys(array_filter($built, static fn($d) => !$d['eligible'])));
        if ($changed) {
            $this->cacheManager->cleanProducts($changed);
        }
        return $totals;
    }

    /**
     * Precompute top-K vector neighbors for re-embedded products (and add reverse links)
     *
     * @param int $storeId
     * @param int[] $productIds
     * @param int[] $ineligible
     * @return void
     */
    private function updateNeighbors(int $storeId, array $productIds, array $ineligible): void
    {
        if ($ineligible) {
            $this->relation->deleteProducts($ineligible);
        }
        $topK = max(1, $this->config->getInt('catalog/similar_top_k', null, 20));
        $websiteId = $this->storeResolver->getWebsiteId($storeId);
        $relations = [];
        foreach ($productIds as $productId) {
            $neighbors = $this->vectorSearch->similarTo($storeId, $websiteId, (int)$productId, $topK);
            $relations[(int)$productId] = $neighbors;
            try {
                $this->relation->addReverse($storeId, Relation::SIMILAR, (int)$productId, $neighbors, $topK);
            } catch (\Throwable $e) {
                $this->logger->warning('Reverse similarity update failed', ['error' => $e]);
            }
        }
        if ($relations) {
            $this->relation->replaceForProducts($storeId, Relation::SIMILAR, $relations);
        }
    }

    /**
     * @param int $storeId
     * @param int $productId
     * @param string $sku
     * @param array $data
     * @return array
     */
    private function row(int $storeId, int $productId, string $sku, array $data): array
    {
        return array_merge([
            'store_id' => $storeId, 'product_id' => $productId, 'sku' => mb_substr($sku, 0, 64),
            'content_hash' => null, 'payload_hash' => null, 'vector' => null, 'model' => null,
            'model_version' => null, 'dimensions' => 0, 'status' => ProductIndex::STATUS_PENDING,
            'sync_status' => 'disabled', 'attempts' => 0, 'last_error' => null, 'indexed_at' => null,
        ], $data);
    }
}
