<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Dashboard;

use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractIndex;

/**
 * Recommendation dashboard: KPIs, unit performance, system health
 */
class Index extends AbstractIndex
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::dashboard';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::dashboard';
    protected const PAGE_TITLE = 'Dashboard';
}
