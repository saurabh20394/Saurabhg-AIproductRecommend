<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Monitor;

use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Mark job errors as resolved
 */
class Resolve extends AbstractErrorAction
{
    /**
     * @inheritdoc
     */
    public function execute()
    {
        $ids = $this->getSelectedIds();
        $this->errorLog->setStatus($ids, ErrorLog::STATUS_RESOLVED);
        $this->messageManager->addSuccessMessage(__('%1 error(s) marked as resolved.', count($ids)));
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
