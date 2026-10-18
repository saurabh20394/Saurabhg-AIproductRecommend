<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Monitor;

use Magento\Backend\App\Action as BackendAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Mass action on the error log grid (selected / all-except-excluded)
 */
abstract class AbstractErrorAction extends BackendAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::reindex';

    /**
     * @param Context $context
     * @param ErrorLog $errorLog
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        protected readonly ErrorLog $errorLog,
        protected readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
    }

    /**
     * @return int[]
     */
    protected function getSelectedIds(): array
    {
        $selected = $this->getRequest()->getParam('selected');
        if (is_array($selected)) {
            return array_map('intval', $selected);
        }
        $excluded = $this->getRequest()->getParam('excluded');
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(ErrorLog::TABLE), ['log_id'])
            ->where('status = ?', ErrorLog::STATUS_OPEN);
        if (is_array($excluded) && $excluded) {
            $select->where('log_id NOT IN (?)', array_map('intval', $excluded));
        }
        return array_map('intval', $connection->fetchCol($select));
    }
}
