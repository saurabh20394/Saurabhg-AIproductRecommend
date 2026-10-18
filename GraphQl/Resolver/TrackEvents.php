<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\GraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * Mutation.aiRecommendationTrackEvents
 */
class TrackEvents implements ResolverInterface
{
    /**
     * @param EventIngestion $ingestion
     */
    public function __construct(
        private readonly EventIngestion $ingestion
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        /** @var ContextInterface $context */
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $customerId = $context->getExtensionAttributes()->getIsCustomer() ? (int)$context->getUserId() : null;
        $visitorId = null;
        $raw = [];
        foreach ((array)($args['events'] ?? []) as $event) {
            if ($visitorId === null && VisitorIdentity::isValid($event['visitorId'] ?? null)) {
                $visitorId = $event['visitorId'];
            }
            $raw[] = [
                'event_id' => $event['eventId'] ?? null,
                'event_type' => $event['eventType'] ?? '',
                'timestamp' => $event['timestamp'] ?? null,
                'sku' => $event['sku'] ?? null,
                'category_id' => $event['categoryId'] ?? null,
                'query' => $event['query'] ?? null,
                'quantity' => $event['quantity'] ?? null,
                'unit_code' => $event['unitCode'] ?? null,
                'position' => $event['position'] ?? null,
                'tracking_token' => $event['trackingToken'] ?? null,
            ];
        }
        $result = $this->ingestion->ingest($raw, $storeId, $customerId ?: null, $visitorId, 'graphql');
        return [
            'accepted' => $result->getAccepted(),
            'rejected' => $result->getRejected(),
            'errors' => $result->getErrors(),
        ];
    }
}
