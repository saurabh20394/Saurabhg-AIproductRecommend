<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * ai_recommendation_campaign resource
 */
class Campaign extends AbstractDb
{
    public const TABLE = 'ai_recommendation_campaign';
    public const ID_FIELD = 'campaign_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, self::ID_FIELD);
    }
}
