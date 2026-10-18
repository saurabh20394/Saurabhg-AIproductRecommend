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
 * Merchant business rule
 */
class Rule extends AbstractEntity
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_rule';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Rule::class);
    }
}
