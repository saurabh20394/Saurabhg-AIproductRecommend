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

/**
 * Recent, time-decayed demand; falls back to popularity when there is no recent activity
 */
class Trending extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $size = $this->fetchSize($limit);
        $trending = $this->productStat->getTop($context->storeId, 'trending_score', $size);
        $set->addMany($trending, 'trending', 'trending');
        if (count($trending) < $limit) {
            $this->addPopular($set, $context, $size, null, 0.5);
        }
        return $set;
    }
}
