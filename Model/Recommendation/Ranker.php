<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Hybrid weighted ranker:
 *   final = Σ w_f × feature_f + merchant_boost + campaign_boost − exclusion_penalty
 *
 * The feature contract (names, 0..1 ranges) is stable so an ML ranker can replace the weighted formula.
 */
class Ranker
{
    public const FEATURES = [
        'behavior', 'similarity', 'affinity', 'popularity', 'trending', 'conversion', 'inventory', 'recency', 'newness', 'ai',
    ];

    /**
     * Type-specific weight profiles; hybrid / personalized use the configured weights
     */
    public const PROFILES = [
        UnitType::POPULAR => ['popularity' => 0.65, 'trending' => 0.1, 'conversion' => 0.1, 'inventory' => 0.1, 'newness' => 0.05],
        UnitType::TRENDING => ['trending' => 0.7, 'popularity' => 0.1, 'conversion' => 0.05, 'inventory' => 0.1, 'newness' => 0.05],
        UnitType::RECENTLY_VIEWED => ['recency' => 1.0],
        UnitType::FREQUENTLY_BOUGHT => ['behavior' => 0.65, 'popularity' => 0.1, 'conversion' => 0.1, 'inventory' => 0.1, 'similarity' => 0.05],
        UnitType::ALSO_VIEWED => ['behavior' => 0.6, 'similarity' => 0.15, 'popularity' => 0.1, 'inventory' => 0.1, 'conversion' => 0.05],
        UnitType::SIMILAR => ['similarity' => 0.7, 'popularity' => 0.1, 'conversion' => 0.05, 'inventory' => 0.1, 'affinity' => 0.05],
        UnitType::COMPLEMENTARY => ['behavior' => 0.45, 'popularity' => 0.2, 'affinity' => 0.2, 'inventory' => 0.1, 'conversion' => 0.05],
        UnitType::NEWEST => ['newness' => 0.8, 'popularity' => 0.1, 'inventory' => 0.1],
    ];

    /**
     * Component => reason code when no strategy-specific reason is known
     */
    private const DEFAULT_REASONS = [
        'popularity' => 'popular',
        'trending' => 'trending',
        'conversion' => 'popular',
        'affinity' => 'based_on_history',
        'newness' => 'new_arrival',
        'recency' => 'recently_viewed',
        'behavior' => 'based_on_history',
        'similarity' => 'similar',
        'ai' => 'recommended',
    ];

    /**
     * Effective weights for a type (variant overrides take precedence)
     *
     * @param string $type
     * @param array<string, float> $configWeights
     * @param array<string, float> $overrides
     * @param bool $remote
     * @return array<string, float>
     */
    public function getWeights(string $type, array $configWeights, array $overrides = [], bool $remote = false): array
    {
        if ($remote) {
            $weights = ['ai' => 1.0];
        } elseif (isset(self::PROFILES[$type])) {
            $weights = self::PROFILES[$type];
        } else {
            $weights = $configWeights + ['recency' => 0.0, 'newness' => 0.02];
        }
        foreach ($overrides as $feature => $weight) {
            if (in_array($feature, self::FEATURES, true) && is_numeric($weight)) {
                $weights[$feature] = (float)$weight;
            }
        }
        return array_filter($weights, static fn($w) => $w != 0.0);
    }

    /**
     * Customer-product affinity from the derived profile (category, brand and price band match)
     *
     * @param array $facts product facts
     * @param array $profile
     * @return float 0..1
     */
    public function affinity(array $facts, array $profile): float
    {
        $categoryScores = $profile['cat'] ?? [];
        $brandScores = $profile['brand'] ?? [];
        if (!$categoryScores && !$brandScores && empty($profile['price_n'])) {
            return 0.0;
        }
        $category = 0.0;
        if ($categoryScores) {
            $max = max($categoryScores);
            foreach ($facts['categories'] ?? [] as $categoryId) {
                $category = max($category, (float)($categoryScores[(string)$categoryId] ?? 0) / $max);
            }
        }
        $brand = 0.0;
        if ($brandScores && !empty($facts['brand'])) {
            $brand = (float)($brandScores[(string)$facts['brand']] ?? 0) / max($brandScores);
        }
        $price = 0.0;
        if (!empty($profile['price_n']) && !empty($facts['price'])) {
            $average = (float)$profile['price_sum'] / (int)$profile['price_n'];
            if ($average > 0) {
                $price = max(0.0, 1.0 - abs((float)$facts['price'] - $average) / $average);
            }
        }
        return round(0.6 * $category + 0.25 * $brand + 0.15 * $price, 6);
    }

    /**
     * @param array $facts
     * @return float
     */
    public function inventory(array $facts): float
    {
        if (empty($facts['in_stock'])) {
            return 0.0;
        }
        return !empty($facts['low_stock']) ? 0.5 : 1.0;
    }

    /**
     * Score candidates
     *
     * @param CandidateSet $set
     * @param array<int, array> $facts eligible products only
     * @param array<int, array> $stats popularity / trending / conversion scores
     * @param array $profile
     * @param array<string, float> $weights
     * @param array<int, float> $boosts merchant + campaign boost per product (already capped)
     * @return array<int, array{final: float, features: array<string, float>, reason: string, boost: float}>
     */
    public function rank(CandidateSet $set, array $facts, array $stats, array $profile, array $weights, array $boosts): array
    {
        $ranked = [];
        foreach ($set->getIds() as $productId) {
            if (!isset($facts[$productId])) {
                continue;
            }
            $components = $set->getScores($productId);
            $reasons = $set->getReasons($productId);
            $features = [];
            foreach (['behavior', 'similarity', 'recency', 'newness', 'ai'] as $component) {
                $features[$component] = min(1.0, (float)($components[$component] ?? 0));
            }
            $features['popularity'] = max((float)($components['popularity'] ?? 0), (float)($stats[$productId]['popularity_score'] ?? 0));
            $features['trending'] = max((float)($components['trending'] ?? 0), (float)($stats[$productId]['trending_score'] ?? 0));
            $features['conversion'] = (float)($stats[$productId]['conversion_score'] ?? 0);
            $features['inventory'] = $this->inventory($facts[$productId]);
            $features['affinity'] = isset($weights['affinity']) ? $this->affinity($facts[$productId], $profile) : 0.0;

            $final = 0.0;
            $best = null;
            $bestValue = 0.0;
            foreach ($weights as $feature => $weight) {
                $contribution = $weight * ($features[$feature] ?? 0.0);
                $final += $contribution;
                if ($feature !== 'inventory' && $contribution > $bestValue) {
                    $bestValue = $contribution;
                    $best = $feature;
                }
            }
            $boost = (float)($boosts[$productId] ?? 0.0);
            $final += $boost;
            $reason = $best !== null ? ($reasons[$best] ?? self::DEFAULT_REASONS[$best] ?? 'recommended') : 'recommended';
            if ($boost > 0 && $boost > $bestValue) {
                $reason = 'featured';
            }
            $ranked[$productId] = [
                'final' => round($final, 6),
                'features' => array_map(static fn($v) => round($v, 4), $features),
                'reason' => $reason,
                'boost' => round($boost, 4),
            ];
        }
        uksort($ranked, static function ($a, $b) use ($ranked) {
            return [$ranked[$b]['final'], $a] <=> [$ranked[$a]['final'], $b];
        });
        return $ranked;
    }
}
