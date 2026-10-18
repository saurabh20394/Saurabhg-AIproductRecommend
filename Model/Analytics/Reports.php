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
 * Dashboard / report queries over the daily aggregates
 *
 *   CTR = clicks / impressions, add-to-cart rate = add-to-carts / clicks,
 *   conversion rate = attributed orders / clicks, attributed AOV = revenue / attributed orders
 */
class Reports
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param string $from Y-m-d
     * @param string $to Y-m-d
     * @param int|null $storeId
     * @return array
     */
    public function getTotals(string $from, string $to, ?int $storeId = null): array
    {
        $row = $this->resourceConnection->getConnection()->fetchRow($this->baseSelect($from, $to, $storeId));
        return $this->withRates($row ?: []);
    }

    /**
     * @param string $from
     * @param string $to
     * @param int|null $storeId
     * @return array[]
     */
    public function getByUnit(string $from, string $to, ?int $storeId = null): array
    {
        $select = $this->baseSelect($from, $to, $storeId)->columns(['unit_code'])->group('unit_code')->order('revenue DESC');
        return array_map([$this, 'withRates'], $this->resourceConnection->getConnection()->fetchAll($select));
    }

    /**
     * @param string $from
     * @param string $to
     * @param int|null $storeId
     * @return array[]
     */
    public function getDaily(string $from, string $to, ?int $storeId = null): array
    {
        $select = $this->baseSelect($from, $to, $storeId)->columns(['period'])->group('period')->order('period ASC');
        return array_map([$this, 'withRates'], $this->resourceConnection->getConnection()->fetchAll($select));
    }

    /**
     * Daily rows per unit (CSV export)
     *
     * @param string $from
     * @param string $to
     * @param int|null $storeId
     * @return array[]
     */
    public function getDailyByUnit(string $from, string $to, ?int $storeId = null): array
    {
        $select = $this->baseSelect($from, $to, $storeId)
            ->columns(['period', 'unit_code'])->group(['period', 'unit_code'])->order(['period ASC', 'unit_code ASC']);
        return array_map([$this, 'withRates'], $this->resourceConnection->getConnection()->fetchAll($select));
    }

    /**
     * @param int $experimentId
     * @return array<int, array> variant ID => metrics
     */
    public function getByVariant(int $experimentId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(Aggregator::TABLE), $this->metricColumns())
            ->columns(['variant_id'])
            ->where('experiment_id = ?', $experimentId)
            ->group('variant_id');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['variant_id']] = $this->withRates($row);
        }
        return $result;
    }

    /**
     * Average order value of orders containing attributed items vs all other orders
     *
     * @param string $from
     * @param string $to
     * @param int|null $storeId
     * @return array{attributed_aov: float, other_aov: float, lift: float|null, attributed_orders: int, other_orders: int}
     */
    public function getAovLift(string $from, string $to, ?int $storeId = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $attributed = $connection->select()
            ->distinct()
            ->from($this->resourceConnection->getTableName('ai_recommendation_conversion'), ['order_id']);
        $select = $connection->select()
            ->from(['o' => $this->resourceConnection->getTableName('sales_order')], [
                'is_attributed' => new \Zend_Db_Expr('IF(c.order_id IS NULL, 0, 1)'),
                'orders' => new \Zend_Db_Expr('COUNT(*)'),
                'aov' => new \Zend_Db_Expr('AVG(o.base_grand_total)'),
            ])
            ->joinLeft(['c' => $attributed], 'c.order_id = o.entity_id', [])
            ->where('o.created_at >= ?', $from . ' 00:00:00')->where('o.created_at <= ?', $to . ' 23:59:59')
            ->where('o.state <> ?', 'canceled')
            ->group('is_attributed');
        if ($storeId) {
            $select->where('o.store_id = ?', $storeId);
        }
        $data = [0 => ['orders' => 0, 'aov' => 0.0], 1 => ['orders' => 0, 'aov' => 0.0]];
        foreach ($connection->fetchAll($select) as $row) {
            $data[(int)$row['is_attributed']] = ['orders' => (int)$row['orders'], 'aov' => (float)$row['aov']];
        }
        return [
            'attributed_aov' => round($data[1]['aov'], 2),
            'other_aov' => round($data[0]['aov'], 2),
            'lift' => $data[0]['aov'] > 0 && $data[1]['orders'] > 0 ? round(($data[1]['aov'] / $data[0]['aov'] - 1) * 100, 2) : null,
            'attributed_orders' => $data[1]['orders'],
            'other_orders' => $data[0]['orders'],
        ];
    }

    /**
     * @param string $from
     * @param string $to
     * @param int|null $storeId
     * @return \Magento\Framework\DB\Select
     */
    private function baseSelect(string $from, string $to, ?int $storeId): \Magento\Framework\DB\Select
    {
        $select = $this->resourceConnection->getConnection()->select()
            ->from($this->resourceConnection->getTableName(Aggregator::TABLE), $this->metricColumns())
            ->where('period >= ?', $from)->where('period <= ?', $to);
        if ($storeId) {
            $select->where('store_id = ?', $storeId);
        }
        return $select;
    }

    /**
     * @return array
     */
    private function metricColumns(): array
    {
        return [
            'impressions' => new \Zend_Db_Expr('COALESCE(SUM(impressions), 0)'),
            'clicks' => new \Zend_Db_Expr('COALESCE(SUM(clicks), 0)'),
            'add_to_carts' => new \Zend_Db_Expr('COALESCE(SUM(add_to_carts), 0)'),
            'orders' => new \Zend_Db_Expr('COALESCE(SUM(orders), 0)'),
            'items' => new \Zend_Db_Expr('COALESCE(SUM(items), 0)'),
            'revenue' => new \Zend_Db_Expr('COALESCE(SUM(revenue), 0)'),
        ];
    }

    /**
     * @param array $row
     * @return array
     */
    public function withRates(array $row): array
    {
        $impressions = (int)($row['impressions'] ?? 0);
        $clicks = (int)($row['clicks'] ?? 0);
        $orders = (int)($row['orders'] ?? 0);
        $revenue = (float)($row['revenue'] ?? 0);
        return array_merge($row, [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'add_to_carts' => (int)($row['add_to_carts'] ?? 0),
            'orders' => $orders,
            'revenue' => round($revenue, 2),
            'ctr' => $impressions ? round($clicks / $impressions * 100, 2) : 0.0,
            'atc_rate' => $clicks ? round((int)($row['add_to_carts'] ?? 0) / $clicks * 100, 2) : 0.0,
            'conversion_rate' => $clicks ? round($orders / $clicks * 100, 2) : 0.0,
            'aov' => $orders ? round($revenue / $orders, 2) : 0.0,
        ]);
    }
}
