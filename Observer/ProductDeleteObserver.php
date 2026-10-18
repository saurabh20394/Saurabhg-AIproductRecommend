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
use Saurabhg\AIproductRecommend\Model\Recommendation\CacheManager;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * catalog_product_delete_after: remove derived data and invalidate affected results
 */
class ProductDeleteObserver implements ObserverInterface
{
    /**
     * @param ProductIndex $productIndex
     * @param Relation $relation
     * @param ProductStat $productStat
     * @param CacheManager $cacheManager
     * @param Logger $logger
     */
    public function __construct(
        private readonly ProductIndex $productIndex,
        private readonly Relation $relation,
        private readonly ProductStat $productStat,
        private readonly CacheManager $cacheManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product || !$product->getId()) {
            return;
        }
        $ids = [(int)$product->getId()];
        try {
            $this->productIndex->deleteProducts($ids);
            $this->relation->deleteProducts($ids);
            $this->productStat->deleteProducts($ids);
            $this->cacheManager->cleanProducts($ids);
        } catch (\Throwable $e) {
            $this->logger->error('Could not clean recommendation data of a deleted product', ['error' => $e]);
        }
    }
}
