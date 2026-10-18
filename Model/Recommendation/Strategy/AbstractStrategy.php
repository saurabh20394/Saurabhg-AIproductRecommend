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
 * Shared retrieval helpers
 */
abstract class AbstractStrategy implements StrategyInterface
{
    /**
     * @param CandidateSetFactory $candidateSetFactory
     * @param Relation $relation
     * @param ProductStat $productStat
     * @param Catalog $catalog
     */
    public function __construct(
        protected readonly CandidateSetFactory $candidateSetFactory,
        protected readonly Relation $relation,
        protected readonly ProductStat $productStat,
        protected readonly Catalog $catalog
    ) {
    }

    /**
     * @return CandidateSet
     */
    protected function newSet(): CandidateSet
    {
        return $this->candidateSetFactory->create();
    }

    /**
     * Over-fetch size so eligibility filtering still leaves enough products
     *
     * @param int $limit
     * @return int
     */
    protected function fetchSize(int $limit): int
    {
        return max(40, $limit * 6);
    }

    /**
     * Popular products (or newest when no behavior/order history exists yet)
     *
     * @param CandidateSet $set
     * @param Context $context
     * @param int $size
     * @param int[]|null $categoryIds
     * @param float $factor
     * @return void
     */
    protected function addPopular(CandidateSet $set, Context $context, int $size, ?array $categoryIds = null, float $factor = 1.0): void
    {
        $popular = $this->productStat->getTop($context->storeId, 'popularity_score', $size, $categoryIds);
        $set->addMany($popular, 'popularity', 'popular', $factor);
        if (count($popular) < $size) {
            $set->addMany(
                $this->catalog->getNewest($context->websiteId, $size, $categoryIds),
                'newness',
                'new_arrival',
                $factor * 0.5
            );
        }
    }

    /**
     * Seed products describing the current page (product, cart, order)
     *
     * @param Context $context
     * @return int[]
     */
    protected function getSeeds(Context $context): array
    {
        return $context->getContextProductIds();
    }
}
