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
 * Business rule condition fields (evaluated against the request context)
 */
class RuleConditionField extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'page_type' => __('Page Type (home, category, product, cart, success, api)'),
            'customer_group' => __('Customer Group ID'),
            'segment' => __('Customer Segment ID'),
            'logged_in' => __('Customer Logged In (1/0)'),
            'context_sku' => __('Current Product SKU'),
            'context_category' => __('Current Category ID (page or product categories)'),
            'cart_sku' => __('Cart Contains SKU'),
            'cart_items_count' => __('Cart Items Count'),
        ];
    }
}
