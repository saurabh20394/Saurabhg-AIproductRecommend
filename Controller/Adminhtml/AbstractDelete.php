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

/**
 * Delete one merchant entity
 */
abstract class AbstractDelete extends Action implements HttpPostActionInterface
{
    /**
     * Generated factory of the entity (model or collection)
     *
     * @var mixed
     */
    protected $modelFactory;

    protected const ENTITY_LABEL = 'Item';

    /**
     * @param Context $context
     * @param mixed $modelFactory
     * @param AbstractDb $resource
     */
    public function __construct(
        Context $context,
        $modelFactory,
        protected readonly AbstractDb $resource
    ) {
        parent::__construct($context);
        $this->modelFactory = $modelFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        /** @var AbstractModel $model */
        $model = $this->modelFactory->create();
        $this->resource->load($model, (int)$this->getRequest()->getParam('id'));
        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('This %1 no longer exists.', __(static::ENTITY_LABEL)));
            return $redirect;
        }
        try {
            $this->resource->delete($model);
            $this->messageManager->addSuccessMessage(__('The %1 has been deleted.', __(static::ENTITY_LABEL)));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
