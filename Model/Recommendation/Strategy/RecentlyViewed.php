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
 * Customer / session specific recently viewed products, most recent first
 */
class RecentlyViewed extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $recent = array_values(array_diff(array_map('intval', $context->profile['recent'] ?? []), [(int)$context->productId]));
        $count = count($recent);
        foreach ($recent as $index => $productId) {
            $set->add($productId, 'recency', 1.0 - $index / max(1, $count + 1), 'recently_viewed');
        }
        return $set;
    }
}
