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
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Grid mass action: delete, or set status (request param "status")
 */
abstract class AbstractMassAction extends Action implements HttpPostActionInterface
{
    /**
     * Generated factory of the entity (model or collection)
     *
     * @var mixed
     */
    protected $collectionFactory;

    protected const ACTION = 'delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param mixed $collectionFactory
     * @param AbstractDb $resource
     */
    public function __construct(
        Context $context,
        protected readonly Filter $filter,
        $collectionFactory,
        protected readonly AbstractDb $resource
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $status = (int)$this->getRequest()->getParam('status');
        $count = 0;
        try {
            /** @var AbstractModel $model */
            foreach ($collection as $model) {
                if (static::ACTION === 'delete') {
                    $this->resource->delete($model);
                } else {
                    $model->setData('status', $status);
                    $this->resource->save($model);
                }
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been updated.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
