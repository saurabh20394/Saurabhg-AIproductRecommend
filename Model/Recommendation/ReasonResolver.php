<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

/**
 * Safe shopper-facing reasons; unknown (e.g. remote) codes collapse to "recommended".
 * Reasons never reveal other customers' behavior or internal model details.
 */
class ReasonResolver
{
    /**
     * Remote reason codes mapped to local codes
     */
    private const ALIASES = [
        'semantic_similarity' => 'similar',
        'similarity' => 'similar',
        'co_purchase' => 'frequently_bought_together',
        'co_view' => 'also_viewed',
        'affinity' => 'based_on_history',
        'personalized' => 'based_on_history',
        'popularity' => 'popular',
        'merchant_boost' => 'featured',
    ];

    /**
     * @param string $code
     * @return string
     */
    public function normalize(string $code): string
    {
        $code = self::ALIASES[$code] ?? $code;
        return array_key_exists($code, $this->getLabels()) ? $code : 'recommended';
    }

    /**
     * @param string $code
     * @return string
     */
    public function getLabel(string $code): string
    {
        return (string)($this->getLabels()[$this->normalize($code)]);
    }

    /**
     * @return array<string, \Magento\Framework\Phrase>
     */
    public function getLabels(): array
    {
        return [
            'frequently_bought_together' => __('Frequently bought together'),
            'also_viewed' => __('Shoppers also viewed'),
            'similar' => __('Similar to this item'),
            'recently_viewed' => __('Recently viewed'),
            'based_on_history' => __('Based on your browsing'),
            'popular' => __('Popular choice'),
            'trending' => __('Trending now'),
            'complementary' => __('Goes well with your selection'),
            'new_arrival' => __('New arrival'),
            'featured' => __('Featured'),
            'recommended' => __('Recommended for you'),
        ];
    }
}
