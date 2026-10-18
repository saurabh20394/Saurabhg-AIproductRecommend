<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Simulator;

use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractIndex;

/**
 * Rule simulation / explainability (read-only: no cache writes, no experiment assignment)
 */
class Index extends AbstractIndex
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::simulator';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::simulator';
    protected const PAGE_TITLE = 'Simulator / Explain';
}
