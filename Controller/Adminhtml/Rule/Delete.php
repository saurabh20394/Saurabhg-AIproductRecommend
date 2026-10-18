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
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractDelete;
use Saurabhg\AIproductRecommend\Model\RuleFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule as EntityResource;

/**
 * Delete of a business rule
 */
class Delete extends AbstractDelete
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::rules';
    protected const ENTITY_LABEL = 'Business Rule';

    /**
     * @param Context $context
     * @param RuleFactory $modelFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, RuleFactory $modelFactory, EntityResource $resource)
    {
        parent::__construct($context, $modelFactory, $resource);
    }
}
