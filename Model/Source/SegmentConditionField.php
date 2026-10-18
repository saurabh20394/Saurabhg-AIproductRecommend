<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

/**
 * Segment condition fields (evaluated against the derived profile)
 */
class SegmentConditionField extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'customer_group' => __('Customer Group ID'),
            'logged_in' => __('Logged In (1/0)'),
            'profile_type' => __('Profile Type (anonymous, guest, customer)'),
            'orders_count' => __('Number of Orders'),
            'lifetime_revenue' => __('Lifetime Revenue'),
            'avg_order_value' => __('Average Order Value'),
            'days_since_last_order' => __('Days Since Last Order'),
            'event_count' => __('Number of Interactions'),
            'top_category' => __('Top Affinity Category IDs'),
            'top_brand' => __('Top Affinity Brands'),
            'avg_viewed_price' => __('Average Viewed Price'),
        ];
    }
}
