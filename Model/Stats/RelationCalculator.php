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
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * Co-purchase (orders) and co-view (visitor histories) relations with cosine-normalized co-occurrence:
 *   score(a, b) = together(a, b) / sqrt(count(a) × count(b))
 */
class RelationCalculator
{
    private const MAX_ITEMS_PER_BASKET = 30;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Relation $relation
     * @param Config $config
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Relation $relation,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $storeId
     * @return int products with co-purchase relations
     */
    public function calculateCoPurchase(int $storeId): int
    {
        $days = max(1, $this->config->getInt('algorithms/co_purchase_window_days', null, 365));
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('sales_order_item'), ['order_id', 'product_id'])
            ->where('store_id = ?', $storeId)
            ->where('parent_item_id IS NULL')
            ->where('product_id IS NOT NULL')
            ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - $days * 86400))
            ->order('order_id');
        $relations = $this->fromBaskets($connection->query($select), 'order_id', 'product_id');
        $this->relation->replaceAll($storeId, Relation::CO_PURCHASE, $relations);
        return count($relations);
    }

    /**
     * @param int $storeId
     * @return int products with co-view relations
     */
    public function calculateCoView(int $storeId): int
    {
        $days = max(1, $this->config->getInt('algorithms/co_view_window_days', null, 60));
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->resourceConnection->getTableName('ai_recommendation_event'), [
                'shopper' => new \Zend_Db_Expr("COALESCE(CONCAT('c', customer_id), visitor_id)"),
                'subject_id',
            ])
            ->where('store_id = ?', $storeId)
            ->where('event_type IN (?)', ['product_view', 'recommendation_click'])
            ->where('subject_id IS NOT NULL')
            ->where('customer_id IS NOT NULL OR visitor_id IS NOT NULL')
            ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - $days * 86400))
            ->order('shopper');
        $relations = $this->fromBaskets($connection->query($select), 'shopper', 'subject_id');
        $this->relation->replaceAll($storeId, Relation::CO_VIEW, $relations);
        return count($relations);
    }

    /**
     * @param \Zend_Db_Statement_Interface $statement rows ordered by basket key
     * @param string $basketKey
     * @param string $productKey
     * @return array<int, array<int, float>>
     */
    private function fromBaskets($statement, string $basketKey, string $productKey): array
    {
        $pairs = [];
        $counts = [];
        $basket = [];
        $current = null;
        while ($row = $statement->fetch()) {
            if ($current !== null && $row[$basketKey] !== $current) {
                $this->addBasket($basket, $pairs, $counts);
                $basket = [];
            }
            $current = $row[$basketKey];
            $basket[(int)$row[$productKey]] = true;
        }
        $this->addBasket($basket, $pairs, $counts);

        $topK = max(1, $this->config->getInt('algorithms/relation_top_k', null, 30));
        $relations = [];
        foreach ($pairs as $productId => $related) {
            $scores = [];
            foreach ($related as $relatedId => $together) {
                $scores[$relatedId] = $together / sqrt($counts[$productId] * $counts[$relatedId]);
            }
            arsort($scores);
            $relations[$productId] = array_slice($scores, 0, $topK, true);
        }
        return $relations;
    }

    /**
     * @param array<int, bool> $basket
     * @param array $pairs
     * @param array $counts
     * @return void
     */
    private function addBasket(array $basket, array &$pairs, array &$counts): void
    {
        $products = array_slice(array_keys($basket), 0, self::MAX_ITEMS_PER_BASKET);
        foreach ($products as $productId) {
            $counts[$productId] = ($counts[$productId] ?? 0) + 1;
        }
        if (count($products) < 2) {
            return;
        }
        foreach ($products as $a) {
            foreach ($products as $b) {
                if ($a !== $b) {
                    $pairs[$a][$b] = ($pairs[$a][$b] ?? 0) + 1;
                }
            }
        }
    }
}
