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
 * Recommendation unit types
 */
class UnitType extends AbstractOptions
{
    public const POPULAR = 'popular';
    public const TRENDING = 'trending';
    public const RECENTLY_VIEWED = 'recently_viewed';
    public const FREQUENTLY_BOUGHT = 'frequently_bought_together';
    public const ALSO_VIEWED = 'also_viewed';
    public const SIMILAR = 'similar';
    public const PERSONALIZED = 'recommended_for_you';
    public const COMPLEMENTARY = 'complementary';
    public const HYBRID = 'hybrid';
    public const NEWEST = 'newest';

    /**
     * Types whose result depends on the visitor profile (cache is keyed per visitor)
     */
    public const PERSONAL_TYPES = [self::RECENTLY_VIEWED, self::PERSONALIZED, self::HYBRID];

    /**
     * Types whose result does not depend on page context (cache is shared across pages)
     */
    public const CONTEXT_FREE_TYPES = [self::POPULAR, self::TRENDING, self::NEWEST];

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            self::POPULAR => __('Most Viewed / Popular'),
            self::TRENDING => __('Trending'),
            self::RECENTLY_VIEWED => __('Recently Viewed'),
            self::FREQUENTLY_BOUGHT => __('Frequently Bought Together'),
            self::ALSO_VIEWED => __('Customers Also Viewed'),
            self::SIMILAR => __('AI Similar Products'),
            self::PERSONALIZED => __('Recommended For You'),
            self::COMPLEMENTARY => __('Complete the Look / Complementary'),
            self::HYBRID => __('Hybrid (all signals)'),
            self::NEWEST => __('New Arrivals'),
        ];
    }
}
