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
 * New arrivals of the website (deterministic Magento fallback)
 */
class Newest extends AbstractStrategy
{
    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $categories = $context->categoryId ? [$context->categoryId] : null;
        $set->addMany($this->catalog->getNewest($context->websiteId, $this->fetchSize($limit), $categories), 'newness', 'new_arrival');
        if ($categories && $set->count() < $limit) {
            $set->addMany($this->catalog->getNewest($context->websiteId, $this->fetchSize($limit)), 'newness', 'new_arrival', 0.5);
        }
        return $set;
    }
}
