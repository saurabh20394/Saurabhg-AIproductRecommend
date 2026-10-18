<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Privacy;

use Magento\Framework\App\ResourceConnection;
use Saurabhg\AIproductRecommend\Model\Analytics\Aggregator;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Event as EventResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Profile as ProfileResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Result;

/**
 * Applies the configured retention policy
 */
class RetentionCleaner
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param EventResource $eventResource
     * @param Result $result
     * @param ErrorLog $errorLog
     * @param Aggregator $aggregator
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EventResource $eventResource,
        private readonly Result $result,
        private readonly ErrorLog $errorLog,
        private readonly Aggregator $aggregator,
        private readonly Config $config
    ) {
    }

    /**
     * @return array<string, int> deleted rows per data set
     */
    public function clean(): array
    {
        $days = max(1, $this->config->getInt('privacy/retention_days', null, 180));
        $before = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $deleted = [];
        foreach ([EventResource::TABLE, EventResource::IMPRESSION_TABLE, EventResource::CLICK_TABLE] as $table) {
            $deleted[$table] = $this->eventResource->purgeBefore($table, $before);
        }
        $analyticsDays = max($days, $this->config->getInt('privacy/analytics_retention_days', null, 730));
        $deleted[EventResource::CONVERSION_TABLE] = $this->eventResource->purgeBefore(
            EventResource::CONVERSION_TABLE,
            gmdate('Y-m-d H:i:s', time() - $analyticsDays * 86400)
        );
        $deleted[Aggregator::TABLE] = $this->aggregator->purgeOlderThan($analyticsDays);

        $profileDays = max(1, $this->config->getInt('privacy/anonymous_profile_days', null, 90));
        $deleted[ProfileResource::TABLE] = $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName(ProfileResource::TABLE),
            ['customer_id IS NULL', 'updated_at < ?' => gmdate('Y-m-d H:i:s', time() - $profileDays * 86400)]
        );
        $deleted[Result::TABLE] = $this->result->purgeExpired();
        $deleted[ErrorLog::TABLE] = $this->errorLog->purgeResolved(30);
        return $deleted;
    }
}
