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
 * Business rule actions applied to recommendation candidates
 */
class RuleActionType extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'boost_sku' => __('Boost SKUs (value: SKUs, amount: boost)'),
            'boost_category' => __('Boost Category IDs (value: IDs, amount: boost)'),
            'boost_brand' => __('Boost Brands (value: labels or option IDs, amount: boost)'),
            'boost_attribute' => __('Boost by Numeric Attribute e.g. margin (value: attribute code, amount: multiplier)'),
            'boost_on_sale' => __('Boost Products with Active Special Price (amount: boost)'),
            'boost_new' => __('Boost New Products (value: days, amount: boost)'),
            'boost_low_stock' => __('Boost Low Stock (amount: boost, negative to penalize)'),
            'pin_sku' => __('Pin SKU (value: SKU, amount: position)'),
            'exclude_sku' => __('Exclude SKUs (value: SKUs)'),
            'exclude_category' => __('Exclude Category IDs (value: IDs)'),
            'exclude_brand' => __('Exclude Brands (value: labels or option IDs)'),
            'exclude_type' => __('Exclude Product Types (value: simple, configurable, ...)'),
            'exclude_out_of_stock' => __('Exclude Out of Stock'),
            'exclude_low_stock' => __('Exclude Low Stock'),
            'exclude_price_below' => __('Exclude Price Below (value: amount)'),
            'exclude_price_above' => __('Exclude Price Above (value: amount)'),
        ];
    }
}
