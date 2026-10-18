<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\ResourceModel\Segment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Saurabhg\AIproductRecommend\Model\Segment as Model;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Segment as EntityResource;

/**
 * Segment collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = EntityResource::ID_FIELD;

    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_segment_collection';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Model::class, EntityResource::class);
    }
}
