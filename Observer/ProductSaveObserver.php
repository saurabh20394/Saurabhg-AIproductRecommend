<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\Recommendation\CacheManager;

/**
 * catalog_product_save_after / catalog_product_attribute_update_before: queue only the affected products
 */
class ProductSaveObserver implements ObserverInterface
{
    /**
     * @param IndexQueue $indexQueue
     * @param CacheManager $cacheManager
     * @param Logger $logger
     */
    public function __construct(
        private readonly IndexQueue $indexQueue,
        private readonly CacheManager $cacheManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $ids = (array)$observer->getEvent()->getData('product_ids');
        $product = $observer->getEvent()->getData('product');
        if ($product && $product->getId()) {
            $ids[] = (int)$product->getId();
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        try {
            $this->cacheManager->cleanProducts($ids);
            $this->indexQueue->enqueue($ids);
        } catch (\Throwable $e) {
            // catalog saves must never fail because of recommendations
            $this->logger->error('Could not queue product reindex', ['error' => $e]);
        }
    }
}
