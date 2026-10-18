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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Edit form page of a merchant entity
 */
abstract class AbstractEdit extends Action implements HttpGetActionInterface
{
    /**
     * Generated factory of the entity (model or collection)
     *
     * @var mixed
     */
    protected $modelFactory;

    protected const MENU_ID = 'Saurabhg_AIproductRecommend::root';
    protected const ENTITY_LABEL = 'Item';

    /**
     * @param Context $context
     * @param mixed $modelFactory generated factory of the entity model
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
        $id = (int)$this->getRequest()->getParam('id');
        /** @var AbstractModel $model */
        $model = $this->modelFactory->create();
        if ($id) {
            $this->resource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This %1 no longer exists.', __(static::ENTITY_LABEL)));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(static::MENU_ID);
        $page->getConfig()->getTitle()->prepend(__('AI Recommendations'));
        $page->getConfig()->getTitle()->prepend(
            $id ? __('Edit %1 "%2"', __(static::ENTITY_LABEL), $this->getTitle($model)) : __('New %1', __(static::ENTITY_LABEL))
        );
        return $page;
    }

    /**
     * @param AbstractModel $model
     * @return string
     */
    protected function getTitle(AbstractModel $model): string
    {
        return (string)($model->getData('name') ?: $model->getData('code') ?: $model->getId());
    }
}
