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
use Saurabhg\AIproductRecommend\Model\ResourceModel\Event as EventResource;

/**
 * Last-touch attribution of add-to-cart and purchases to recommendation clicks within the configured window
 */
class AttributionService
{
    /**
     * @param EventResource $eventResource
     * @param Config $config
     */
    public function __construct(
        private readonly EventResource $eventResource,
        private readonly Config $config
    ) {
    }

    /**
     * Derived recommendation_add_to_cart rows for add_to_cart events preceded by a recommendation click
     *
     * @param array $rows add_to_cart event rows
     * @return array
     */
    public function attributeAddToCart(array $rows): array
    {
        $derived = [];
        foreach ($this->groupByShopper($rows) as $group) {
            $first = reset($group);
            $clicks = $this->eventResource->getLastClicks(
                array_column($group, 'subject_id'),
                $first['visitor_id'],
                $first['customer_id'] ? (int)$first['customer_id'] : null,
                $this->windowStart((int)$first['store_id'])
            );
            foreach ($group as $row) {
                $click = $clicks[(int)$row['subject_id']] ?? null;
                if (!$click) {
                    continue;
                }
                $derived[] = array_merge($row, [
                    'event_uuid' => substr($row['event_uuid'], 0, 60) . ':rac',
                    'event_type' => 'recommendation_add_to_cart',
                    'unit_code' => $click['unit_code'],
                    'position' => $click['position'],
                    'experiment_id' => $click['experiment_id'],
                    'variant_id' => $click['variant_id'],
                ]);
            }
        }
        return $derived;
    }

    /**
     * Conversion rows for purchased order items preceded by a recommendation click
     *
     * @param array $rows purchase event rows (order_id, order_item_id, subject_id, qty, revenue)
     * @return array
     */
    public function attributePurchases(array $rows): array
    {
        $conversions = [];
        foreach ($this->groupByShopper($rows) as $group) {
            $first = reset($group);
            $clicks = $this->eventResource->getLastClicks(
                array_column($group, 'subject_id'),
                $first['visitor_id'],
                $first['customer_id'] ? (int)$first['customer_id'] : null,
                $this->windowStart((int)$first['store_id'])
            );
            foreach ($group as $row) {
                $click = $clicks[(int)$row['subject_id']] ?? null;
                if (!$click || empty($row['order_item_id'])) {
                    continue;
                }
                $conversions[] = [
                    'event_uuid' => 'conv-' . $row['order_item_id'],
                    'order_id' => (int)$row['order_id'],
                    'order_item_id' => (int)$row['order_item_id'],
                    'unit_code' => (string)$click['unit_code'],
                    'product_id' => (int)$row['subject_id'],
                    'qty' => (float)($row['qty'] ?? 0),
                    'revenue' => (float)($row['revenue'] ?? 0),
                    'visitor_id' => $row['visitor_id'],
                    'customer_id' => $row['customer_id'],
                    'experiment_id' => $click['experiment_id'],
                    'variant_id' => $click['variant_id'],
                    'store_id' => (int)$row['store_id'],
                    'attribution_type' => 'click',
                    'created_at' => $row['created_at'],
                ];
            }
        }
        return $conversions;
    }

    /**
     * @param array $rows
     * @return array<string, array>
     */
    private function groupByShopper(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (empty($row['subject_id']) || (empty($row['visitor_id']) && empty($row['customer_id']))) {
                continue;
            }
            $groups[($row['customer_id'] ?? '') . '|' . ($row['visitor_id'] ?? '')][] = $row;
        }
        return $groups;
    }

    /**
     * @param int $storeId
     * @return string
     */
    private function windowStart(int $storeId): string
    {
        $days = max(1, $this->config->getInt('privacy/attribution_window_days', $storeId, 7));
        return gmdate('Y-m-d H:i:s', time() - $days * 86400);
    }
}
