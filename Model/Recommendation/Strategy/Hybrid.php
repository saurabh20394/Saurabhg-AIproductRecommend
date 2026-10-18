<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation\Strategy;

use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSet;
use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSetFactory;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * Hybrid: union of all candidate sources; the hybrid ranker combines their scores
 */
class Hybrid extends AbstractStrategy
{
    /**
     * @param CandidateSetFactory $candidateSetFactory
     * @param Relation $relation
     * @param ProductStat $productStat
     * @param Catalog $catalog
     * @param FrequentlyBoughtTogether $frequentlyBought
     * @param AlsoViewed $alsoViewed
     * @param Similar $similar
     * @param Personalized $personalized
     * @param Trending $trending
     */
    public function __construct(
        CandidateSetFactory $candidateSetFactory,
        Relation $relation,
        ProductStat $productStat,
        Catalog $catalog,
        private readonly FrequentlyBoughtTogether $frequentlyBought,
        private readonly AlsoViewed $alsoViewed,
        private readonly Similar $similar,
        private readonly Personalized $personalized,
        private readonly Trending $trending
    ) {
        parent::__construct($candidateSetFactory, $relation, $productStat, $catalog);
    }

    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        if ($context->getContextProductIds()) {
            $set->merge($this->frequentlyBought->collect($context, $limit));
            $set->merge($this->alsoViewed->collect($context, $limit));
            $set->merge($this->similar->collect($context, $limit));
        }
        if ($context->hasProfile()) {
            $set->merge($this->personalized->collect($context, $limit));
        }
        if ($set->count() < $limit * 2) {
            $set->merge($this->trending->collect($context, $limit));
        }
        return $set;
    }
}
