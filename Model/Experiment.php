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
 * A/B experiment definition
 */
class Experiment extends AbstractEntity
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_experiment';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Experiment::class);
    }
}
