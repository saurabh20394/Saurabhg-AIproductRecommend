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
 * Behavior events, impressions, clicks and conversions (append-only, idempotent by UUID)
 */
class Event extends AbstractDb
{
    public const TABLE = 'ai_recommendation_event';
    public const IMPRESSION_TABLE = 'ai_recommendation_impression';
    public const CLICK_TABLE = 'ai_recommendation_click';
    public const CONVERSION_TABLE = 'ai_recommendation_conversion';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'event_id');
    }

    /**
     * Insert rows whose event_uuid is new; returns the rows actually inserted
     *
     * @param string $table one of the TABLE constants
     * @param array $rows
     * @return array
     */
    public function insertNew(string $table, array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $connection = $this->getConnection();
        $tableName = $this->getTable($table);
        $uuids = array_column($rows, 'event_uuid');
        $existing = $connection->fetchCol(
            $connection->select()->from($tableName, ['event_uuid'])->where('event_uuid IN (?)', $uuids)
        );
        $existing = array_flip($existing);
        $new = [];
        foreach ($rows as $row) {
            if (!isset($existing[$row['event_uuid']])) {
                $existing[$row['event_uuid']] = true;
                $new[] = $row;
            }
        }
        if ($new) {
            $columns = array_keys($new[0]);
            $normalized = array_map(static fn($row) => array_replace(array_fill_keys($columns, null), $row), $new);
            $connection->insertArray($tableName, $columns, $normalized, \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_IGNORE);
        }
        return $new;
    }

    /**
     * Insert rows ignoring unique-key duplicates (conversions are unique per order item)
     *
     * @param string $table
     * @param array $rows
     * @return void
     */
    public function insertIgnore(string $table, array $rows): void
    {
        if (!$rows) {
            return;
        }
        $columns = array_keys($rows[0]);
        $normalized = array_map(static fn($row) => array_replace(array_fill_keys($columns, null), $row), $rows);
        $this->getConnection()->insertArray(
            $this->getTable($table),
            $columns,
            $normalized,
            \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_IGNORE
        );
    }

    /**
     * Latest recommendation clicks per product for a shopper within a window (last-touch attribution)
     *
     * @param int[] $productIds
     * @param string|null $visitorId
     * @param int|null $customerId
     * @param string $since
     * @return array<int, array> product ID => click row
     */
    public function getLastClicks(array $productIds, ?string $visitorId, ?int $customerId, string $since): array
    {
        if (!$productIds || (!$visitorId && !$customerId)) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::CLICK_TABLE))
            ->where('product_id IN (?)', array_map('intval', $productIds))
            ->where('created_at >= ?', $since)
            ->order('created_at ASC');
        $who = [];
        if ($customerId) {
            $who[] = $connection->quoteInto('customer_id = ?', $customerId);
        }
        if ($visitorId) {
            $who[] = $connection->quoteInto('visitor_id = ?', $visitorId);
        }
        $select->where(implode(' OR ', $who));
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['product_id']] = $row;
        }
        return $result;
    }

    /**
     * Link anonymous history of a visitor to a customer after login (idempotent)
     *
     * @param string $visitorId
     * @param int $customerId
     * @return void
     */
    public function assignVisitorToCustomer(string $visitorId, int $customerId): void
    {
        $connection = $this->getConnection();
        foreach ([self::TABLE, self::IMPRESSION_TABLE, self::CLICK_TABLE] as $table) {
            $connection->update(
                $this->getTable($table),
                ['customer_id' => $customerId],
                ['visitor_id = ?' => $visitorId, 'customer_id IS NULL']
            );
        }
    }

    /**
     * Anonymize all behavioral rows of a shopper (privacy erasure)
     *
     * @param int|null $customerId
     * @param string[] $visitorIds
     * @return int affected rows
     */
    public function anonymize(?int $customerId, array $visitorIds): int
    {
        $connection = $this->getConnection();
        $affected = 0;
        $tables = [self::TABLE, self::IMPRESSION_TABLE, self::CLICK_TABLE, self::CONVERSION_TABLE];
        foreach ($tables as $table) {
            $bind = ['customer_id' => null, 'visitor_id' => null];
            if ($table === self::TABLE) {
                // search terms may contain personal data
                $bind['search_query'] = null;
            }
            if ($customerId) {
                $affected += $connection->update($this->getTable($table), $bind, ['customer_id = ?' => $customerId]);
            }
            if ($visitorIds) {
                $affected += $connection->update($this->getTable($table), $bind, ['visitor_id IN (?)' => $visitorIds]);
            }
        }
        return $affected;
    }

    /**
     * Retention purge in batches
     *
     * @param string $table
     * @param string $before
     * @param int $batch
     * @return int deleted rows
     */
    public function purgeBefore(string $table, string $before, int $batch = 10000): int
    {
        $connection = $this->getConnection();
        $tableName = $this->getTable($table);
        $deleted = 0;
        do {
            $ids = $connection->fetchCol(
                $connection->select()->from($tableName, [$table === self::TABLE ? 'event_id' : 'id'])
                    ->where('created_at < ?', $before)->limit($batch)
            );
            if ($ids) {
                $deleted += $connection->delete(
                    $tableName,
                    [($table === self::TABLE ? 'event_id' : 'id') . ' IN (?)' => $ids]
                );
            }
        } while (count($ids) === $batch);
        return $deleted;
    }
}
