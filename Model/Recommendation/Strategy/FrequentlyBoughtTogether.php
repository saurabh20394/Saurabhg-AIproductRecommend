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
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * Co-purchase relationships of the current product / cart / order
 */
class FrequentlyBoughtTogether extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $seeds = $this->getSeeds($context);
        $set->addMany(
            $this->relation->getRelated($context->storeId, Relation::CO_PURCHASE, $seeds, $this->fetchSize($limit)),
            'behavior',
            'frequently_bought_together'
        );
        return $set->normalize('behavior');
    }
}
