<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

/**
 * Customer segment definition
 */
class Segment extends AbstractEntity
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_segment';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Segment::class);
    }
}
