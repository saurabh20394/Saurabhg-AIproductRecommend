<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Api;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\EventInterface;
use Saurabhg\AIproductRecommend\Api\Data\EventSubmitResultInterface;
use Saurabhg\AIproductRecommend\Api\EventManagementInterface;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * REST event ingestion (headless): visitor ID from the payload, customer only from a customer token
 */
class EventManagement implements EventManagementInterface
{
    /**
     * @param EventIngestion $ingestion
     * @param UserContextInterface $userContext
     * @param StoreManagerInterface $storeManager
     * @param string $source
     */
    public function __construct(
        private readonly EventIngestion $ingestion,
        private readonly UserContextInterface $userContext,
        private readonly StoreManagerInterface $storeManager,
        private readonly string $source = 'api'
    ) {
    }

    /**
     * @inheritdoc
     */
    public function submit(array $events): EventSubmitResultInterface
    {
        $customerId = (int)$this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER
            ? (int)$this->userContext->getUserId() : null;
        $visitorId = null;
        $raw = [];
        foreach ($events as $event) {
            if (!$event instanceof EventInterface) {
                continue;
            }
            $candidate = $event->getVisitorId();
            if ($visitorId === null && VisitorIdentity::isValid($candidate)) {
                $visitorId = $candidate;
            }
            $raw[] = [
                'event_id' => $event->getEventId(),
                'event_type' => $event->getEventType(),
                'timestamp' => $event->getTimestamp(),
                'sku' => $event->getSku(),
                'product_id' => $event->getProductId(),
                'category_id' => $event->getCategoryId(),
                'query' => $event->getQuery(),
                'quantity' => $event->getQuantity(),
                'unit_code' => $event->getUnitCode(),
                'position' => $event->getPosition(),
                'tracking_token' => $event->getTrackingToken(),
            ];
        }
        return $this->ingestion->ingest(
            $raw,
            (int)$this->storeManager->getStore()->getId(),
            $customerId ?: null,
            $visitorId,
            $this->source
        );
    }
}
