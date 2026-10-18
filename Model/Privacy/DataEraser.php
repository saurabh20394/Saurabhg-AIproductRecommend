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
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Recommendation\CacheManager;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Event as EventResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as ExperimentResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Profile as ProfileResource;

/**
 * Customer / visitor erasure: deletes derived profiles (including merged visitor profiles) and
 * experiment assignments, anonymizes behavioral rows. Aggregated analytics and order data are kept.
 */
class DataEraser
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param EventResource $eventResource
     * @param ExperimentResource $experimentResource
     * @param CacheManager $cacheManager
     * @param Logger $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EventResource $eventResource,
        private readonly ExperimentResource $experimentResource,
        private readonly CacheManager $cacheManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param int $customerId
     * @return int affected rows
     */
    public function forgetCustomer(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $profiles = $this->resourceConnection->getTableName(ProfileResource::TABLE);
        $profileId = (int)$connection->fetchOne(
            $connection->select()->from($profiles, ['profile_id'])->where('customer_id = ?', $customerId)
        );
        $visitorIds = $profileId ? $connection->fetchCol(
            $connection->select()->from($profiles, ['visitor_id'])->where('merged_into = ?', $profileId)
                ->where('visitor_id IS NOT NULL')
        ) : [];
        $affected = $this->eventResource->anonymize($customerId, $visitorIds);
        $affected += $connection->delete($profiles, ['customer_id = ?' => $customerId]);
        if ($visitorIds) {
            $affected += $connection->delete($profiles, ['visitor_id IN (?)' => $visitorIds]);
        }
        $this->experimentResource->deleteAssignments(array_merge(
            ['c:' . $customerId],
            array_map(static fn($id) => 'v:' . $id, $visitorIds)
        ));
        $this->cacheManager->cleanAll();
        $this->logger->info('Recommendation data erased for a customer', ['rows' => $affected]);
        return $affected;
    }

    /**
     * @param string $visitorId
     * @return int
     */
    public function forgetVisitor(string $visitorId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $affected = $this->eventResource->anonymize(null, [$visitorId]);
        $affected += $connection->delete(
            $this->resourceConnection->getTableName(ProfileResource::TABLE),
            ['visitor_id = ?' => $visitorId]
        );
        $this->experimentResource->deleteAssignments(['v:' . $visitorId]);
        return $affected;
    }
}
