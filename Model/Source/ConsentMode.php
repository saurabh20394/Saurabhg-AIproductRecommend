<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Tracking consent modes
 */
class ConsentMode extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            Config::CONSENT_MAGENTO => __('Magento Cookie Restriction Mode'),
            Config::CONSENT_CUSTOM => __('Custom Consent Cookie (CMP)'),
            Config::CONSENT_NONE => __('No Consent Required'),
        ];
    }
}
