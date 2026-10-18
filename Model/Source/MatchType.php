<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

/**
 * Segment condition aggregator
 */
class MatchType extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return ['all' => __('All conditions (AND)'), 'any' => __('Any condition (OR)')];
    }
}
