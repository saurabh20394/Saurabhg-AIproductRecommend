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
 * Experiment lifecycle
 */
class ExperimentStatus extends AbstractOptions
{
    public const DRAFT = 0;
    public const RUNNING = 1;
    public const STOPPED = 2;

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [self::DRAFT => __('Draft'), self::RUNNING => __('Running'), self::STOPPED => __('Stopped')];
    }
}
