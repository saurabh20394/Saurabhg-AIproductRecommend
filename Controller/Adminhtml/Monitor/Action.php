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
use Saurabhg\AIproductRecommend\Cron\Jobs;
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;

/**
 * Operational actions: queue full reindex, retry failed products, process pending, recalculate signals
 */
class Action extends BackendAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::reindex';

    /**
     * @param Context $context
     * @param IndexQueue $indexQueue
     * @param ProductIndex $productIndex
     * @param Jobs $jobs
     */
    public function __construct(
        Context $context,
        private readonly IndexQueue $indexQueue,
        private readonly ProductIndex $productIndex,
        private readonly Jobs $jobs
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        try {
            switch ((string)$this->getRequest()->getParam('do')) {
                case 'reindex':
                    $count = $this->indexQueue->enqueueAll();
                    $this->messageManager->addSuccessMessage(__('%1 product(s) queued for embedding and sync.', $count));
                    break;
                case 'retry_failed':
                    $count = $this->productIndex->retryFailed();
                    $this->messageManager->addSuccessMessage(__('%1 failed product(s) will be retried.', $count));
                    break;
                case 'process_pending':
                    $totals = $this->jobs->processPendingIndex();
                    $this->messageManager->addSuccessMessage(__(
                        'Processed: %1 embedded, %2 unchanged, %3 skipped, %4 failed.',
                        $totals['indexed'] ?? 0,
                        $totals['unchanged'] ?? 0,
                        $totals['skipped'] ?? 0,
                        $totals['failed'] ?? 0
                    ));
                    break;
                case 'calculate':
                    $this->jobs->calculateStats();
                    $this->jobs->calculateRelations();
                    $this->jobs->aggregateAnalytics();
                    $this->messageManager->addSuccessMessage(__('Popularity, trending, co-purchase, co-view and analytics were recalculated.'));
                    break;
                default:
                    $this->messageManager->addErrorMessage(__('Unknown action.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
