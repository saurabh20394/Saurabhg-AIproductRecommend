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
 * Condition operators shared by rules and segments
 */
class Operator extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'eq' => __('is'),
            'neq' => __('is not'),
            'in' => __('is one of (comma-separated)'),
            'nin' => __('is not one of (comma-separated)'),
            'gt' => __('greater than'),
            'gte' => __('greater than or equal'),
            'lt' => __('less than'),
            'lte' => __('less than or equal'),
            'contains' => __('contains'),
        ];
    }
}
