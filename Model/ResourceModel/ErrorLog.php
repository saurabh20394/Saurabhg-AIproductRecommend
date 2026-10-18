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
use Saurabhg\AIproductRecommend\Logger\Logger;

/**
 * Failed asynchronous jobs, retryable from the admin monitor (dead-letter store)
 */
class ErrorLog extends AbstractDb
{
    public const TABLE = 'ai_recommendation_error_log';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    public const JOB_EVENT = 'event';
    public const JOB_PRODUCT_INDEX = 'product_index';
    public const JOB_STATS = 'stats';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'log_id');
    }

    /**
     * @param string $jobType
     * @param string $message
     * @param array $payload must not contain secrets
     * @param string|null $correlationId
     * @return void
     */
    public function add(string $jobType, string $message, array $payload, ?string $correlationId = null): void
    {
        $this->getConnection()->insert($this->getMainTable(), [
            'job_type' => $jobType,
            'correlation_id' => $correlationId,
            'message' => Logger::sanitize($message),
            'payload' => mb_substr((string)json_encode($payload), 0, 1000000),
            'status' => self::STATUS_OPEN,
        ]);
    }

    /**
     * @param int[] $ids
     * @return array
     */
    public function getByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->getConnection();
        return $connection->fetchAll(
            $connection->select()->from($this->getMainTable())->where('log_id IN (?)', array_map('intval', $ids))
        );
    }

    /**
     * @param int[] $ids
     * @param string $status
     * @param bool $incrementAttempts
     * @return void
     */
    public function setStatus(array $ids, string $status, bool $incrementAttempts = false): void
    {
        if (!$ids) {
            return;
        }
        $bind = ['status' => $status];
        if ($incrementAttempts) {
            $bind['attempts'] = new \Zend_Db_Expr('attempts + 1');
        }
        $this->getConnection()->update($this->getMainTable(), $bind, ['log_id IN (?)' => $ids]);
    }

    /**
     * @return int
     */
    public function countOpen(): int
    {
        $connection = $this->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where('status = ?', self::STATUS_OPEN)
        );
    }

    /**
     * @param int $days
     * @return int
     */
    public function purgeResolved(int $days): int
    {
        return $this->getConnection()->delete($this->getMainTable(), [
            'status = ?' => self::STATUS_RESOLVED,
            'updated_at < ?' => gmdate('Y-m-d H:i:s', time() - $days * 86400),
        ]);
    }
}
