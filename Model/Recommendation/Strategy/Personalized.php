<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation\Strategy;

use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSet;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * Recommended For You: neighbors of the shopper's recent / carted / purchased products plus
 * popular products of the strongest affinity categories; popularity for cold start
 */
class Personalized extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $size = $this->fetchSize($limit);
        $profile = $context->profile;
        $seeds = array_values(array_unique(array_merge(
            array_slice(array_map('intval', $profile['cart'] ?? []), 0, 5),
            array_slice(array_map('intval', $profile['recent'] ?? []), 0, 8),
            array_slice(array_map('intval', $profile['purchased'] ?? []), 0, 5),
            $context->getContextProductIds()
        )));

        if ($seeds) {
            $set->addMany($this->relation->getRelated($context->storeId, Relation::CO_PURCHASE, $seeds, $size), 'behavior', 'based_on_history', 1.0, true);
            $set->addMany($this->relation->getRelated($context->storeId, Relation::CO_VIEW, $seeds, $size), 'behavior', 'based_on_history', 0.6, true);
            $set->addMany($this->relation->getRelated($context->storeId, Relation::SIMILAR, $seeds, $size), 'similarity', 'based_on_history');
            $set->normalize('behavior');
        }
        $topCategories = array_map('intval', ProfileService::top($profile, 'cat', 3));
        if ($topCategories) {
            $set->addMany($this->productStat->getTop($context->storeId, 'popularity_score', $size, $topCategories), 'popularity', 'based_on_history', 0.8);
        }
        if ($set->count() < $limit) {
            $this->addPopular($set, $context, $size);
        }
        // already owned / in cart / just viewed products are not recommended back
        $set->remove(array_merge(
            array_map('intval', $profile['purchased'] ?? []),
            array_map('intval', $profile['cart'] ?? []),
            array_slice(array_map('intval', $profile['recent'] ?? []), 0, 3)
        ));
        return $set;
    }
}
