<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Campaign;

use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractIndex;

/**
 * Campaigns grid
 */
class Index extends AbstractIndex
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::campaigns';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::campaigns';
    protected const PAGE_TITLE = 'Campaigns';
}
