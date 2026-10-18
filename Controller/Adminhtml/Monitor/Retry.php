<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Monitor;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Saurabhg\AIproductRecommend\Model\Catalog\ProductIndexer;
use Saurabhg\AIproductRecommend\Model\Event\EventProcessor;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Re-run failed jobs from their stored payload
 */
class Retry extends AbstractErrorAction
{
    /**
     * @param Context $context
     * @param ErrorLog $errorLog
     * @param ResourceConnection $resourceConnection
     * @param EventProcessor $eventProcessor
     * @param ProductIndexer $productIndexer
     */
    public function __construct(
        Context $context,
        ErrorLog $errorLog,
        ResourceConnection $resourceConnection,
        private readonly EventProcessor $eventProcessor,
        private readonly ProductIndexer $productIndexer
    ) {
        parent::__construct($context, $errorLog, $resourceConnection);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $ok = $failed = 0;
        foreach ($this->errorLog->getByIds($this->getSelectedIds()) as $row) {
            if ($row['status'] !== ErrorLog::STATUS_OPEN) {
                continue;
            }
            $payload = json_decode((string)$row['payload'], true) ?: [];
            try {
                switch ($row['job_type']) {
                    case ErrorLog::JOB_EVENT:
                        $this->eventProcessor->process($payload);
                        break;
                    case ErrorLog::JOB_PRODUCT_INDEX:
                        $result = $this->productIndexer->reindex(
                            (array)($payload['product_ids'] ?? []),
                            isset($payload['store_id']) ? [(int)$payload['store_id']]
                                : (!empty($payload['store_ids']) ? array_map('intval', $payload['store_ids']) : null)
                        );
                        if ($result['failed'] > 0) {
                            throw new \RuntimeException((string)__('%1 product(s) failed again.', $result['failed']));
                        }
                        break;
                    default:
                        // scheduled calculations simply run again on their next schedule
                        break;
                }
                $this->errorLog->setStatus([(int)$row['log_id']], ErrorLog::STATUS_RESOLVED, true);
                $ok++;
            } catch (\Throwable $e) {
                $this->errorLog->setStatus([(int)$row['log_id']], ErrorLog::STATUS_OPEN, true);
                $failed++;
            }
        }
        $this->messageManager->addSuccessMessage(__('Retried: %1 succeeded, %2 failed.', $ok, $failed));
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
