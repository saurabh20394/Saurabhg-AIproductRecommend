<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Campaign;

use Magento\Backend\App\Action\Context;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractDelete;
use Saurabhg\AIproductRecommend\Model\CampaignFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Campaign as EntityResource;

/**
 * Delete of a campaign
 */
class Delete extends AbstractDelete
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::campaigns';
    protected const ENTITY_LABEL = 'Campaign';

    /**
     * @param Context $context
     * @param CampaignFactory $modelFactory
     * @param EntityResource $resource
     */
    public function __construct(Context $context, CampaignFactory $modelFactory, EntityResource $resource)
    {
        parent::__construct($context, $modelFactory, $resource);
    }
}
