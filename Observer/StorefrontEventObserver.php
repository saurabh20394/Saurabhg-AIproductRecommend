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
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * Server-side cart / wishlist events (reliable, not affected by full page cache):
 * checkout_cart_product_add_after, sales_quote_remove_item, wishlist_add_product
 */
class StorefrontEventObserver implements ObserverInterface
{
    /**
     * @param EventIngestion $ingestion
     * @param VisitorIdentity $visitorIdentity
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param string $eventType
     */
    public function __construct(
        private readonly EventIngestion $ingestion,
        private readonly VisitorIdentity $visitorIdentity,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger,
        private readonly string $eventType = 'add_to_cart'
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $visitorId = $this->visitorIdentity->getVisitorId($storeId);
            $customerId = $this->visitorIdentity->getCustomerId($storeId);
            if (!$visitorId && !$customerId) {
                return;
            }
            $event = $observer->getEvent();
            $item = $event->getData('quote_item') ?? $event->getData('item');
            $product = $event->getData('product') ?? ($item ? $item->getProduct() : null);
            if ($item && $item->getParentItem()) {
                $item = $item->getParentItem();
                $product = $item->getProduct();
            }
            $productId = $item && $item->getProductId() ? (int)$item->getProductId() : ($product ? (int)$product->getId() : 0);
            if (!$productId) {
                return;
            }
            $this->ingestion->ingest([[
                'event_type' => $this->eventType,
                'product_id' => $productId,
                'quantity' => $item ? (float)($item->getQty() ?: 1) : 1.0,
            ]], $storeId, $customerId, $visitorId, 'server', true);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Tracking of %s failed', $this->eventType), ['error' => $e]);
        }
    }
}
