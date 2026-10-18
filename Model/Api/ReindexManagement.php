<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Api;

use Saurabhg\AIproductRecommend\Api\ReindexManagementInterface;
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Queues reindex work (processed by the product index consumer / reconciliation cron)
 */
class ReindexManagement implements ReindexManagementInterface
{
    /**
     * @param IndexQueue $indexQueue
     * @param StoreResolver $storeResolver
     */
    public function __construct(
        private readonly IndexQueue $indexQueue,
        private readonly StoreResolver $storeResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function reindex(?array $productIds = null, ?array $storeIds = null): int
    {
        $enabled = $this->storeResolver->getStoreIds();
        $stores = $storeIds ? array_values(array_intersect(array_map('intval', $storeIds), $enabled)) : $enabled;
        if (!$stores) {
            return 0;
        }
        return $productIds
            ? $this->indexQueue->enqueue(array_map('intval', $productIds), $stores)
            : $this->indexQueue->enqueueAll($stores);
    }
}
