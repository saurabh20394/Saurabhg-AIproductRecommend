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
 * Storefront placements of recommendation units
 */
class Placement extends AbstractOptions
{
    public const WIDGET_ONLY = 'widget_only';

    /**
     * Placement code => page type
     */
    public const PAGE_TYPES = [
        'home_top' => 'home',
        'home_bottom' => 'home',
        'category_top' => 'category',
        'category_bottom' => 'category',
        'product_info' => 'product',
        'product_bottom' => 'product',
        'cart_bottom' => 'cart',
        'success_bottom' => 'success',
    ];

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'home_top' => __('Homepage - Top of Content'),
            'home_bottom' => __('Homepage - Bottom of Content'),
            'category_top' => __('Category Page - Above Products'),
            'category_bottom' => __('Category Page - Below Products'),
            'product_info' => __('Product Page - Below Product Info'),
            'product_bottom' => __('Product Page - Bottom of Content'),
            'cart_bottom' => __('Shopping Cart - Bottom'),
            'success_bottom' => __('Order Success Page'),
            self::WIDGET_ONLY => __('CMS Widget / API Only'),
        ];
    }
}
