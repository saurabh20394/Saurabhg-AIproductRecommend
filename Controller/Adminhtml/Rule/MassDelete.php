<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractMassAction;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule as EntityResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule\CollectionFactory;

/**
 * Mass delete of business rules
 */
class MassDelete extends AbstractMassAction
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::rules';
    protected const ACTION = 'delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory, EntityResource $resource)
    {
        parent::__construct($context, $filter, $collectionFactory, $resource);
    }
}
