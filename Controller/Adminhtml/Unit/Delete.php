<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Unit;

use Magento\Backend\App\Action\Context;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractDelete;
use Saurabhg\AIproductRecommend\Model\UnitFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit as EntityResource;

/**
 * Delete of a recommendation unit
 */
class Delete extends AbstractDelete
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::units';
    protected const ENTITY_LABEL = 'Recommendation Unit';

    /**
     * @param Context $context
     * @param UnitFactory $modelFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, UnitFactory $modelFactory, EntityResource $resource)
    {
        parent::__construct($context, $modelFactory, $resource);
    }
}
