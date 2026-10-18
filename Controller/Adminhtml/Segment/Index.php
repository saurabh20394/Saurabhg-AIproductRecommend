<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Segment;

use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractIndex;

/**
 * Customer Segments grid
 */
class Index extends AbstractIndex
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::segments';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::segments';
    protected const PAGE_TITLE = 'Customer Segments';
}
