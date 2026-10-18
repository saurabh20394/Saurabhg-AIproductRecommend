<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation\Strategy;

use Saurabhg\AIproductRecommend\Model\Catalog\VectorSearch;
use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSet;
use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSetFactory;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * AI similar products: precomputed vector neighbors -> live vector search -> same-category cold start
 */
class Similar extends AbstractStrategy
{
    /**
     * @param CandidateSetFactory $candidateSetFactory
     * @param Relation $relation
     * @param ProductStat $productStat
     * @param Catalog $catalog
     * @param VectorSearch $vectorSearch
     */
    public function __construct(
        CandidateSetFactory $candidateSetFactory,
        Relation $relation,
        ProductStat $productStat,
        Catalog $catalog,
        private readonly VectorSearch $vectorSearch
    ) {
        parent::__construct($candidateSetFactory, $relation, $productStat, $catalog);
    }

    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $seeds = $this->getSeeds($context);
        if (!$seeds) {
            return $set;
        }
        $size = $this->fetchSize($limit);
        $set->addMany($this->relation->getRelated($context->storeId, Relation::SIMILAR, $seeds, $size), 'similarity', 'similar');

        if ($set->count() < $limit && $context->productId) {
            $set->addMany(
                $this->vectorSearch->similarTo($context->storeId, $context->websiteId, $context->productId, $size, 500),
                'similarity',
                'similar'
            );
        }
        if ($set->count() < $limit) {
            // cold start: same-category products until embeddings / behavior accumulate
            $categories = $context->productCategoryIds ?: ($context->categoryId ? [$context->categoryId] : []);
            if ($categories) {
                $this->addPopular($set, $context, $size, $categories, 0.6);
            }
        }
        return $set;
    }
}
