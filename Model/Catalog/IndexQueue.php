<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Catalog;

use Magento\Framework\MessageQueue\PublisherInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Queues only affected products for (re)indexing; pending rows are also picked up by cron reconciliation
 */
class IndexQueue
{
    public const TOPIC = 'saurabhg.airecommend.product.index';

    private const MESSAGE_SIZE = 200;

    /**
     * @param ProductIndex $productIndex
     * @param Catalog $catalog
     * @param StoreResolver $storeResolver
     * @param PublisherInterface $publisher
     * @param Logger $logger
     */
    public function __construct(
        private readonly ProductIndex $productIndex,
        private readonly Catalog $catalog,
        private readonly StoreResolver $storeResolver,
        private readonly PublisherInterface $publisher,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int[]|null $storeIds
     * @return int queued products
     */
    public function enqueue(array $productIds, ?array $storeIds = null): int
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $storeIds ??= $this->storeResolver->getStoreIds();
        if (!$productIds || !$storeIds) {
            return 0;
        }
        $this->productIndex->markPending($productIds, $storeIds);
        foreach (array_chunk($productIds, self::MESSAGE_SIZE) as $chunk) {
            try {
                $this->publisher->publish(self::TOPIC, (string)json_encode(['product_ids' => $chunk, 'store_ids' => $storeIds]));
            } catch (\Throwable $e) {
                // rows stay pending: the reconciliation cron indexes them
                $this->logger->warning('Index queue unavailable; products stay pending for cron', ['error' => $e]);
                break;
            }
        }
        return count($productIds);
    }

    /**
     * Queue every product of the enabled stores' websites
     *
     * @param int[]|null $storeIds
     * @return int
     */
    public function enqueueAll(?array $storeIds = null): int
    {
        $storeIds ??= $this->storeResolver->getStoreIds();
        $total = 0;
        foreach ($storeIds as $storeId) {
            $ids = $this->catalog->getWebsiteProductIds($this->storeResolver->getWebsiteId((int)$storeId));
            $total += $this->enqueue($ids, [(int)$storeId]);
        }
        return $total;
    }
}
