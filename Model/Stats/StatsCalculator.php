<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Stats;

use Magento\Framework\App\ResourceConnection;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;

/**
 * Popularity (log-normalized weighted interactions), trending (time-decayed demand) and
 * conversion (smoothed purchases per view). Orders come from Magento sales tables, the source of truth.
 */
class StatsCalculator
{
    private const WEIGHT_VIEW = 1.0;
    private const WEIGHT_CART = 3.0;
    private const WEIGHT_WISHLIST = 2.0;
    private const WEIGHT_PURCHASE = 5.0;
    private const CONVERSION_PRIOR = 10;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ProductStat $productStat
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductStat $productStat,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $storeId
     * @return int products with stats
     */
    public function calculate(int $storeId): int
    {
        $popularDays = max(1, $this->config->getInt('algorithms/popular_window_days', null, 30));
        $trendingDays = max(1, $this->config->getInt('algorithms/trending_window_days', null, 7));
        $halfLife = max(1, $this->config->getInt('algorithms/trending_half_life_hours', null, 48));

        $counts = $this->getEventCounts($storeId, gmdate('Y-m-d H:i:s', time() - $popularDays * 86400));
        foreach ($this->getOrderedQty($storeId, gmdate('Y-m-d H:i:s', time() - $popularDays * 86400)) as $productId => $qty) {
            $counts[$productId]['purchases'] = $qty;
        }
        $trending = $this->getTrendingRaw($storeId, gmdate('Y-m-d H:i:s', time() - $trendingDays * 86400), $halfLife);

        $popularRaw = [];
        $conversionRaw = [];
        foreach ($counts as $productId => $row) {
            $views = (int)($row['views'] ?? 0);
            $purchases = (float)($row['purchases'] ?? 0);
            $popularRaw[$productId] = $views * self::WEIGHT_VIEW + (int)($row['carts'] ?? 0) * self::WEIGHT_CART
                + (int)($row['wishlists'] ?? 0) * self::WEIGHT_WISHLIST + $purchases * self::WEIGHT_PURCHASE;
            $conversionRaw[$productId] = $purchases / ($views + self::CONVERSION_PRIOR);
        }
        $maxPopular = $popularRaw ? max($popularRaw) : 0;
        $maxTrending = $trending ? max($trending) : 0;
        $maxConversion = $conversionRaw ? max($conversionRaw) : 0;

        $rows = [];
        foreach (array_unique(array_merge(array_keys($counts), array_keys($trending))) as $productId) {
            $popularity = $maxPopular > 0 ? log1p($popularRaw[$productId] ?? 0) / log1p($maxPopular) : 0;
            $rows[] = [
                'store_id' => $storeId,
                'product_id' => (int)$productId,
                'views' => (int)($counts[$productId]['views'] ?? 0),
                'carts' => (int)($counts[$productId]['carts'] ?? 0),
                'wishlists' => (int)($counts[$productId]['wishlists'] ?? 0),
                'purchases' => round((float)($counts[$productId]['purchases'] ?? 0), 4),
                'popularity_score' => round($popularity, 6),
                'trending_score' => $maxTrending > 0 ? round(($trending[$productId] ?? 0) / $maxTrending, 6) : 0,
                'conversion_score' => $maxConversion > 0 ? round(($conversionRaw[$productId] ?? 0) / $maxConversion, 6) : 0,
            ];
        }
        $this->productStat->replaceStore($storeId, $rows);
        return count($rows);
    }

    /**
     * @param int $storeId
     * @param string $since
     * @return array<int, array{views: int, carts: int, wishlists: int}>
     */
    private function getEventCounts(int $storeId, string $since): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_event'), [
                'subject_id',
                'views' => new \Zend_Db_Expr("SUM(event_type = 'product_view')"),
                'carts' => new \Zend_Db_Expr("SUM(event_type = 'add_to_cart')"),
                'wishlists' => new \Zend_Db_Expr("SUM(event_type = 'wishlist_add')"),
            ])
            ->where('store_id = ?', $storeId)
            ->where('created_at >= ?', $since)
            ->where('subject_id IS NOT NULL')
            ->where('event_type IN (?)', ['product_view', 'add_to_cart', 'wishlist_add'])
            ->group('subject_id');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['subject_id']] = [
                'views' => (int)$row['views'], 'carts' => (int)$row['carts'], 'wishlists' => (int)$row['wishlists'],
            ];
        }
        return $result;
    }

    /**
     * @param int $storeId
     * @param string $since
     * @return array<int, float>
     */
    private function getOrderedQty(int $storeId, string $since): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['i' => $this->resourceConnection->getTableName('sales_order_item')], [
                'product_id', 'qty' => new \Zend_Db_Expr('SUM(i.qty_ordered)'),
            ])
            ->join(['o' => $this->resourceConnection->getTableName('sales_order')], 'o.entity_id = i.order_id', [])
            ->where('i.store_id = ?', $storeId)
            ->where('i.parent_item_id IS NULL')
            ->where('i.product_id IS NOT NULL')
            ->where('i.created_at >= ?', $since)
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('i.product_id');
        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * Exponentially decayed weighted activity
     *
     * @param int $storeId
     * @param string $since
     * @param int $halfLifeHours
     * @return array<int, float>
     */
    private function getTrendingRaw(int $storeId, string $since, int $halfLifeHours): array
    {
        $connection = $this->resourceConnection->getConnection();
        $decay = sprintf('POW(0.5, TIMESTAMPDIFF(HOUR, %%s, UTC_TIMESTAMP()) / %d)', $halfLifeHours);
        $events = $connection->select()
            ->from($this->resourceConnection->getTableName('ai_recommendation_event'), [
                'subject_id',
                'score' => new \Zend_Db_Expr(sprintf(
                    "SUM(CASE event_type WHEN 'product_view' THEN %F WHEN 'add_to_cart' THEN %F WHEN 'wishlist_add' THEN %F ELSE 0 END * %s)",
                    self::WEIGHT_VIEW,
                    self::WEIGHT_CART,
                    self::WEIGHT_WISHLIST,
                    sprintf($decay, 'created_at')
                )),
            ])
            ->where('store_id = ?', $storeId)
            ->where('created_at >= ?', $since)
            ->where('subject_id IS NOT NULL')
            ->where('event_type IN (?)', ['product_view', 'add_to_cart', 'wishlist_add'])
            ->group('subject_id');
        $scores = array_map('floatval', $connection->fetchPairs($events));

        $orders = $connection->select()
            ->from(['i' => $this->resourceConnection->getTableName('sales_order_item')], [
                'product_id',
                'score' => new \Zend_Db_Expr(sprintf('SUM(i.qty_ordered * %F * %s)', self::WEIGHT_PURCHASE, sprintf($decay, 'i.created_at'))),
            ])
            ->where('i.store_id = ?', $storeId)
            ->where('i.parent_item_id IS NULL')
            ->where('i.product_id IS NOT NULL')
            ->where('i.created_at >= ?', $since)
            ->group('i.product_id');
        foreach ($connection->fetchPairs($orders) as $productId => $score) {
            $scores[(int)$productId] = ($scores[(int)$productId] ?? 0) + (float)$score;
        }
        return $scores;
    }
}
