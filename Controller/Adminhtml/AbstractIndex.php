<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Admin page with active menu and title (grids, dashboard, tools)
 */
abstract class AbstractIndex extends Action implements HttpGetActionInterface
{
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::root';
    protected const PAGE_TITLE = 'AI Recommendations';

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(static::MENU_ID);
        $page->getConfig()->getTitle()->prepend(__('AI Recommendations'));
        $page->getConfig()->getTitle()->prepend(__(static::PAGE_TITLE));
        return $page;
    }
}
