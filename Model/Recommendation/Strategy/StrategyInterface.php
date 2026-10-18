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
 * Candidate retrieval of one recommendation algorithm (ranking happens afterwards)
 */
interface StrategyInterface
{
    /**
     * Components: behavior, similarity, recency, newness, popularity, trending (0..1 after normalization)
     *
     * @param Context $context
     * @param int $limit number of products finally needed; strategies over-fetch for filtering
     * @return CandidateSet
     */
    public function collect(Context $context, int $limit): CandidateSet;
}
