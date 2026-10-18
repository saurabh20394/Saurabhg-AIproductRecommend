<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Experiment;

use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractIndex;

/**
 * A/B Experiments grid
 */
class Index extends AbstractIndex
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::experiments';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::experiments';
    protected const PAGE_TITLE = 'A/B Experiments';
}
