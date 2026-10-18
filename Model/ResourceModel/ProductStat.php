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
 * Precomputed popularity / trending / conversion scores
 */
class ProductStat extends AbstractDb
{
    public const TABLE = 'ai_recommendation_product_stat';

    private const SCORE_COLUMNS = ['popularity_score', 'trending_score', 'conversion_score'];

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'product_id');
    }

    /**
     * Top products by a score column, optionally restricted to categories
     *
     * @param int $storeId
     * @param string $column popularity_score / trending_score / conversion_score
     * @param int $limit
     * @param int[]|null $categoryIds
     * @return array<int, float> product ID => score
     */
    public function getTop(int $storeId, string $column, int $limit, ?array $categoryIds = null): array
    {
        if (!in_array($column, self::SCORE_COLUMNS, true)) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['s' => $this->getMainTable()], ['product_id', $column])
            ->where('s.store_id = ?', $storeId)
            ->where('s.' . $column . ' > 0')
            ->order('s.' . $column . ' DESC')
            ->limit($limit);
        if ($categoryIds !== null) {
            if (!$categoryIds) {
                return [];
            }
            $select->join(
                ['ccp' => $this->getTable('catalog_category_product')],
                'ccp.product_id = s.product_id',
                []
            )->where('ccp.category_id IN (?)', array_map('intval', $categoryIds))
                ->group(['s.product_id', 's.' . $column]);
        }
        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * @param int $storeId
     * @param int[] $productIds
     * @return array<int, array{popularity_score: float, trending_score: float, conversion_score: float}>
     */
    public function getScores(int $storeId, array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), array_merge(['product_id'], self::SCORE_COLUMNS))
            ->where('store_id = ?', $storeId)
            ->where('product_id IN (?)', array_map('intval', $productIds));
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['product_id']] = [
                'popularity_score' => (float)$row['popularity_score'],
                'trending_score' => (float)$row['trending_score'],
                'conversion_score' => (float)$row['conversion_score'],
            ];
        }
        return $result;
    }

    /**
     * Atomically replace all stats of a store
     *
     * @param int $storeId
     * @param array $rows
     * @return void
     */
    public function replaceStore(int $storeId, array $rows): void
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $connection->delete($this->getMainTable(), ['store_id = ?' => $storeId]);
            foreach (array_chunk($rows, 1000) as $chunk) {
                $connection->insertMultiple($this->getMainTable(), $chunk);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function hasData(int $storeId): bool
    {
        $connection = $this->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['product_id'])->where('store_id = ?', $storeId)->limit(1)
        );
    }

    /**
     * @param int[] $productIds
     * @return void
     */
    public function deleteProducts(array $productIds): void
    {
        if ($productIds) {
            $this->getConnection()->delete($this->getMainTable(), ['product_id IN (?)' => $productIds]);
        }
    }
}
