<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Event;

use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\TrackingToken;

/**
 * Validates and normalizes raw events (requirements §11 event model). Identity (visitor / customer)
 * always comes from the server-side resolved request, never from the event payload of storefront clients.
 */
class EventValidator
{
    public const CLIENT_TYPES = [
        'product_view', 'category_view', 'search', 'add_to_cart', 'remove_from_cart', 'wishlist_add',
        'recommendation_impression', 'recommendation_click', 'recommendation_add_to_cart',
    ];

    public const SERVER_TYPES = ['purchase'];

    private const PRODUCT_TYPES = [
        'product_view', 'add_to_cart', 'remove_from_cart', 'wishlist_add', 'purchase',
        'recommendation_impression', 'recommendation_click', 'recommendation_add_to_cart',
    ];

    private const UNIT_TYPES = ['recommendation_impression', 'recommendation_click', 'recommendation_add_to_cart'];

    /**
     * @param Config $config
     * @param TrackingToken $trackingToken
     */
    public function __construct(
        private readonly Config $config,
        private readonly TrackingToken $trackingToken
    ) {
    }

    /**
     * @param array $raw event fields (event_id, event_type, timestamp, sku, product_id, category_id, query,
     *                   quantity, unit_code, position, tracking_token; trusted only: order_id, order_item_id, revenue)
     * @param int $storeId
     * @param int|null $customerId
     * @param string|null $visitorId
     * @param string $source web / api / graphql / server
     * @param bool $trusted server-generated event
     * @return array normalized row
     * @throws \InvalidArgumentException
     */
    public function normalize(
        array $raw,
        int $storeId,
        ?int $customerId,
        ?string $visitorId,
        string $source,
        bool $trusted = false
    ): array {
        $type = (string)($raw['event_type'] ?? '');
        $allowed = $trusted ? array_merge(self::CLIENT_TYPES, self::SERVER_TYPES) : self::CLIENT_TYPES;
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported event type "%s".', mb_substr($type, 0, 40)));
        }
        if (($type === 'category_view' && !$this->config->isSetFlag('tracking/track_category', $storeId))
            || ($type === 'search' && !$this->config->isSetFlag('tracking/track_search', $storeId))
            || ($type === 'wishlist_add' && !$this->config->isSetFlag('tracking/track_wishlist', $storeId))
        ) {
            throw new \InvalidArgumentException(sprintf('Tracking of "%s" is disabled.', $type));
        }
        if (!$customerId && !$visitorId) {
            throw new \InvalidArgumentException('Event has no permitted visitor or customer identity.');
        }

        $eventId = isset($raw['event_id']) ? (string)$raw['event_id'] : '';
        if ($eventId === '' || !preg_match('/^[A-Za-z0-9_\-:.]{8,64}$/', $eventId)) {
            if (!$trusted && $eventId !== '') {
                throw new \InvalidArgumentException('Invalid event_id.');
            }
            $eventId = 'e' . bin2hex(random_bytes(15));
        }

        $timestamp = isset($raw['timestamp']) && is_numeric($raw['timestamp']) ? (int)$raw['timestamp'] : time();
        if ($timestamp > 10000000000) {
            $timestamp = (int)floor($timestamp / 1000); // milliseconds
        }
        if ($timestamp < time() - 86400 || $timestamp > time() + 300) {
            $timestamp = time();
        }

        $row = [
            'event_uuid' => $eventId,
            'event_type' => $type,
            'subject_id' => isset($raw['product_id']) && (int)$raw['product_id'] > 0 ? (int)$raw['product_id'] : null,
            'sku' => isset($raw['sku']) && is_scalar($raw['sku']) ? mb_substr(trim((string)$raw['sku']), 0, 64) : null,
            'category_id' => isset($raw['category_id']) && (int)$raw['category_id'] > 0 ? (int)$raw['category_id'] : null,
            'search_query' => null,
            'qty' => null,
            'revenue' => null,
            'order_id' => null,
            'visitor_id' => $visitorId,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'unit_code' => null,
            'position' => null,
            'experiment_id' => null,
            'variant_id' => null,
            'source' => $source,
            'created_at' => gmdate('Y-m-d H:i:s', $timestamp),
        ];
        if ($row['sku'] === '') {
            $row['sku'] = null;
        }
        if (in_array($type, self::PRODUCT_TYPES, true) && !$row['subject_id'] && !$row['sku']) {
            throw new \InvalidArgumentException(sprintf('Event "%s" requires sku or product_id.', $type));
        }
        if ($type === 'category_view' && !$row['category_id']) {
            throw new \InvalidArgumentException('category_view requires category_id.');
        }
        if ($type === 'search') {
            $query = trim(strip_tags((string)($raw['query'] ?? '')));
            if ($query === '') {
                throw new \InvalidArgumentException('search requires query.');
            }
            $row['search_query'] = mb_substr(preg_replace('/\s+/u', ' ', $query), 0, 128);
        }
        if (isset($raw['quantity']) && is_numeric($raw['quantity'])) {
            $row['qty'] = max(0.0, min(10000.0, (float)$raw['quantity']));
        }
        if (isset($raw['position']) && is_numeric($raw['position'])) {
            $row['position'] = max(1, min(100, (int)$raw['position']));
        }

        if (in_array($type, self::UNIT_TYPES, true)) {
            $token = $this->trackingToken->decode(isset($raw['tracking_token']) ? (string)$raw['tracking_token'] : null);
            if ($token !== null && $token['store_id'] === $storeId) {
                $row['unit_code'] = mb_substr($token['unit_code'], 0, 64);
                $row['experiment_id'] = $token['experiment_id'];
                $row['variant_id'] = $token['variant_id'];
                $row['request_id'] = mb_substr($token['request_id'], 0, 32);
            } elseif (isset($raw['unit_code']) && preg_match('/^[A-Za-z0-9_\-:]{1,64}$/', (string)$raw['unit_code'])) {
                $row['unit_code'] = (string)$raw['unit_code'];
            } else {
                throw new \InvalidArgumentException(sprintf('Event "%s" requires a valid tracking_token or unit_code.', $type));
            }
        }

        if ($trusted) {
            foreach (['order_id', 'order_item_id'] as $key) {
                if (isset($raw[$key])) {
                    $row[$key] = (int)$raw[$key];
                }
            }
            if (isset($raw['revenue']) && is_numeric($raw['revenue'])) {
                $row['revenue'] = round((float)$raw['revenue'], 4);
            }
            foreach (['unit_code', 'experiment_id', 'variant_id'] as $key) {
                if (isset($raw[$key]) && $row[$key] === null) {
                    $row[$key] = $raw[$key];
                }
            }
        }
        return $row;
    }
}
