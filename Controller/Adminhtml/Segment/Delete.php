<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Segment;

use Magento\Backend\App\Action\Context;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractDelete;
use Saurabhg\AIproductRecommend\Model\SegmentFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Segment as EntityResource;

/**
 * Delete of a customer segment
 */
class Delete extends AbstractDelete
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::segments';
    protected const ENTITY_LABEL = 'Customer Segment';

    /**
     * @param Context $context
     * @param SegmentFactory $modelFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, SegmentFactory $modelFactory, EntityResource $resource)
    {
        parent::__construct($context, $modelFactory, $resource);
    }
}
