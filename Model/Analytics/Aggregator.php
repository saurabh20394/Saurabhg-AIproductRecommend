<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Analytics;

use Magento\Framework\App\ResourceConnection;

/**
 * Builds idempotent daily aggregates (UTC days) of impressions, clicks, add-to-carts and attributed revenue
 */
class Aggregator
{
    public const TABLE = 'ai_recommendation_analytics_daily';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Re-aggregate the given UTC days (inclusive)
     *
     * @param string $fromDate Y-m-d
     * @param string $toDate Y-m-d
     * @return int aggregate rows written
     */
    public function aggregate(string $fromDate, string $toDate): int
    {
        $connection = $this->resourceConnection->getConnection();
        $from = $fromDate . ' 00:00:00';
        $to = $toDate . ' 23:59:59';
        $rows = [];
        $merge = static function (array $source, string $metric) use (&$rows) {
            foreach ($source as $row) {
                $key = implode('|', [$row['period'], $row['store_id'], $row['unit_code'], $row['experiment_id'], $row['variant_id']]);
                $rows[$key] ??= [
                    'period' => $row['period'], 'store_id' => (int)$row['store_id'], 'unit_code' => (string)$row['unit_code'],
                    'experiment_id' => (int)$row['experiment_id'], 'variant_id' => (int)$row['variant_id'],
                    'impressions' => 0, 'clicks' => 0, 'add_to_carts' => 0, 'orders' => 0, 'items' => 0, 'revenue' => 0,
                ];
                foreach (explode(',', $metric) as $column) {
                    $rows[$key][$column] = $row[$column];
                }
            }
        };
        $dimensions = [
            'period' => new \Zend_Db_Expr('DATE(created_at)'),
            'store_id',
            'unit_code',
            'experiment_id' => new \Zend_Db_Expr('COALESCE(experiment_id, 0)'),
            'variant_id' => new \Zend_Db_Expr('COALESCE(variant_id, 0)'),
        ];
        $group = ['period', 'store_id', 'unit_code', 'experiment_id', 'variant_id'];

        $merge($connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_impression'), $dimensions + ['impressions' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('created_at >= ?', $from)->where('created_at <= ?', $to)->group($group)), 'impressions');
        $merge($connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_click'), $dimensions + ['clicks' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('created_at >= ?', $from)->where('created_at <= ?', $to)->group($group)), 'clicks');
        $merge($connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_event'), $dimensions + ['add_to_carts' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('event_type = ?', 'recommendation_add_to_cart')
            ->where('unit_code IS NOT NULL')
            ->where('created_at >= ?', $from)->where('created_at <= ?', $to)->group($group)), 'add_to_carts');
        $merge($connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_conversion'), $dimensions + [
                'orders' => new \Zend_Db_Expr('COUNT(DISTINCT order_id)'),
                'items' => new \Zend_Db_Expr('SUM(qty)'),
                'revenue' => new \Zend_Db_Expr('SUM(revenue)'),
            ])
            ->where('created_at >= ?', $from)->where('created_at <= ?', $to)->group($group)), 'orders,items,revenue');

        $table = $this->resourceConnection->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table, ['period >= ?' => $fromDate, 'period <= ?' => $toDate]);
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                $connection->insertMultiple($table, $chunk);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        return count($rows);
    }

    /**
     * @param int $days
     * @return int
     */
    public function purgeOlderThan(int $days): int
    {
        return $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName(self::TABLE),
            ['period < ?' => gmdate('Y-m-d', time() - $days * 86400)]
        );
    }
}
