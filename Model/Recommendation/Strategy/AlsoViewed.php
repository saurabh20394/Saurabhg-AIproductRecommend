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
 * Customers who viewed this also viewed (co-view relationships)
 */
class AlsoViewed extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $set->addMany(
            $this->relation->getRelated($context->storeId, Relation::CO_VIEW, $this->getSeeds($context), $this->fetchSize($limit)),
            'behavior',
            'also_viewed'
        );
        return $set->normalize('behavior');
    }
}
