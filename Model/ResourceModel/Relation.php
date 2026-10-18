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
 * Product-to-product relations: co_purchase, co_view, similar
 */
class Relation extends AbstractDb
{
    public const TABLE = 'ai_recommendation_product_relation';

    public const CO_PURCHASE = 'co_purchase';
    public const CO_VIEW = 'co_view';
    public const SIMILAR = 'similar';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'product_id');
    }

    /**
     * Related products of one or more source products, scores summed across sources
     *
     * @param int $storeId
     * @param string $type
     * @param int[] $productIds
     * @param int $limit
     * @return array<int, float> related ID => score, highest first
     */
    public function getRelated(int $storeId, string $type, array $productIds, int $limit = 100): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['related_id', 'score' => new \Zend_Db_Expr('SUM(score)')])
            ->where('store_id = ?', $storeId)
            ->where('relation_type = ?', $type)
            ->where('product_id IN (?)', $productIds)
            ->where('related_id NOT IN (?)', $productIds)
            ->group('related_id')
            ->order('score DESC')
            ->limit($limit);
        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * Replace all relations of a type in a store
     *
     * @param int $storeId
     * @param string $type
     * @param array<int, array<int, float>> $relations product ID => [related ID => score]
     * @return void
     */
    public function replaceAll(int $storeId, string $type, array $relations): void
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $connection->delete($this->getMainTable(), ['store_id = ?' => $storeId, 'relation_type = ?' => $type]);
            $this->insertRelations($storeId, $type, $relations);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Replace the relations of specific source products
     *
     * @param int $storeId
     * @param string $type
     * @param array<int, array<int, float>> $relations
     * @return void
     */
    public function replaceForProducts(int $storeId, string $type, array $relations): void
    {
        if (!$relations) {
            return;
        }
        $this->getConnection()->delete($this->getMainTable(), [
            'store_id = ?' => $storeId,
            'relation_type = ?' => $type,
            'product_id IN (?)' => array_keys($relations),
        ]);
        $this->insertRelations($storeId, $type, $relations);
    }

    /**
     * Add reverse links (neighbor -> product) and trim each neighbor to its top-K
     *
     * @param int $storeId
     * @param string $type
     * @param int $productId
     * @param array<int, float> $neighbors
     * @param int $topK
     * @return void
     */
    public function addReverse(int $storeId, string $type, int $productId, array $neighbors, int $topK): void
    {
        if (!$neighbors) {
            return;
        }
        $connection = $this->getConnection();
        $rows = [];
        foreach ($neighbors as $neighborId => $score) {
            $rows[] = [
                'store_id' => $storeId, 'relation_type' => $type,
                'product_id' => (int)$neighborId, 'related_id' => $productId, 'score' => round((float)$score, 6),
            ];
        }
        $connection->insertOnDuplicate($this->getMainTable(), $rows, ['score']);
        foreach (array_keys($neighbors) as $neighborId) {
            $threshold = $connection->fetchOne(
                $connection->select()->from($this->getMainTable(), ['score'])
                    ->where('store_id = ?', $storeId)->where('relation_type = ?', $type)
                    ->where('product_id = ?', (int)$neighborId)
                    ->order('score DESC')->limit(1, $topK)
            );
            if ($threshold !== false) {
                $connection->delete($this->getMainTable(), [
                    'store_id = ?' => $storeId, 'relation_type = ?' => $type,
                    'product_id = ?' => (int)$neighborId, 'score <= ?' => $threshold,
                ]);
            }
        }
    }

    /**
     * @param int[] $productIds
     * @return void
     */
    public function deleteProducts(array $productIds): void
    {
        if (!$productIds) {
            return;
        }
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['product_id IN (?)' => $productIds]);
        $connection->delete($this->getMainTable(), ['related_id IN (?)' => $productIds]);
    }

    /**
     * @param int $storeId
     * @param string $type
     * @return int
     */
    public function countRelations(int $storeId, string $type): int
    {
        $connection = $this->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), [new \Zend_Db_Expr('COUNT(DISTINCT product_id)')])
                ->where('store_id = ?', $storeId)->where('relation_type = ?', $type)
        );
    }

    /**
     * @param int $storeId
     * @param string $type
     * @param array $relations
     * @return void
     */
    private function insertRelations(int $storeId, string $type, array $relations): void
    {
        $rows = [];
        foreach ($relations as $productId => $related) {
            foreach ($related as $relatedId => $score) {
                if ((int)$relatedId === (int)$productId) {
                    continue;
                }
                $rows[] = [
                    'store_id' => $storeId, 'relation_type' => $type, 'product_id' => (int)$productId,
                    'related_id' => (int)$relatedId, 'score' => round((float)$score, 6),
                ];
                if (count($rows) >= 1000) {
                    $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows, ['score']);
                    $rows = [];
                }
            }
        }
        if ($rows) {
            $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows, ['score']);
        }
    }
}
