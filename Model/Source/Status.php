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
 * Enabled / Disabled
 */
class Status extends AbstractOptions
{
    public const ENABLED = 1;
    public const DISABLED = 0;

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [self::ENABLED => __('Enabled'), self::DISABLED => __('Disabled')];
    }
}
