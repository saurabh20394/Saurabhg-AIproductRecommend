<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Experiment;

use Magento\Backend\App\Action\Context;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractEdit;
use Saurabhg\AIproductRecommend\Model\ExperimentFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as EntityResource;

/**
 * Edit form of a a/b experiment
 */
class Edit extends AbstractEdit
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::experiments';
    protected const MENU_ID = 'Saurabhg_AIproductRecommend::experiments';
    protected const ENTITY_LABEL = 'A/B Experiment';

    /**
     * @param Context $context
     * @param ExperimentFactory $modelFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, ExperimentFactory $modelFactory, EntityResource $resource)
    {
        parent::__construct($context, $modelFactory, $resource);
    }
}
