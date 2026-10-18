<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Observer;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Personalization\ConsentChecker;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * checkout_submit_all_after (storefront, REST / GraphQL, multishipping, admin order creation):
 * purchase events of saved orders; attribution happens asynchronously in the consumer
 */
class OrderPlaceObserver implements ObserverInterface
{
    /**
     * @param EventIngestion $ingestion
     * @param VisitorIdentity $visitorIdentity
     * @param ConsentChecker $consentChecker
     * @param Config $config
     * @param State $appState
     * @param Logger $logger
     */
    public function __construct(
        private readonly EventIngestion $ingestion,
        private readonly VisitorIdentity $visitorIdentity,
        private readonly ConsentChecker $consentChecker,
        private readonly Config $config,
        private readonly State $appState,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $orders = (array)$observer->getEvent()->getData('orders');
        if ($observer->getEvent()->getData('order')) {
            $orders[] = $observer->getEvent()->getData('order');
        }
        foreach ($orders as $order) {
            if ($order instanceof Order && $order->getId()) {
                $this->track($order);
            }
        }
    }

    /**
     * @param Order $order
     * @return void
     */
    private function track(Order $order): void
    {
        try {
            $storeId = (int)$order->getStoreId();
            if (!$this->config->isTrackingEnabled($storeId)) {
                return;
            }
            $visitorId = null;
            if ($this->isFrontend()) {
                if (!$this->consentChecker->hasConsent($storeId)) {
                    return;
                }
                $visitorId = $this->visitorIdentity->getVisitorId($storeId);
            }
            $customerId = $order->getCustomerId() ? (int)$order->getCustomerId() : null;
            $events = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $events[] = [
                    'event_id' => 'ord-' . $item->getId(),
                    'event_type' => 'purchase',
                    'product_id' => (int)$item->getProductId(),
                    'quantity' => (float)$item->getQtyOrdered(),
                    'order_id' => (int)$order->getId(),
                    'order_item_id' => (int)$item->getId(),
                    'revenue' => max(0.0, (float)$item->getBaseRowTotal() - (float)$item->getBaseDiscountAmount()),
                ];
            }
            if ($events && ($customerId || $visitorId)) {
                $this->ingestion->ingest($events, $storeId, $customerId, $visitorId, 'server', true);
            }
        } catch (\Throwable $e) {
            // checkout must never fail because of recommendations
            $this->logger->error('Purchase tracking failed', ['error' => $e]);
        }
    }

    /**
     * @return bool
     */
    private function isFrontend(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
