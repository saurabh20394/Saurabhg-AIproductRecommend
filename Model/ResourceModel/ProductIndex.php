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
 * Embedding vectors and catalog sync state per store view
 */
class ProductIndex extends AbstractDb
{
    public const TABLE = 'ai_recommendation_product_index';

    public const STATUS_PENDING = 'pending';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const MAX_ATTEMPTS = 5;

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'product_id');
    }

    /**
     * Flag products for (re)indexing in the given stores
     *
     * @param int[] $productIds
     * @param int[] $storeIds
     * @return void
     */
    public function markPending(array $productIds, array $storeIds): void
    {
        $rows = [];
        foreach ($storeIds as $storeId) {
            foreach ($productIds as $productId) {
                $rows[] = [
                    'store_id' => (int)$storeId, 'product_id' => (int)$productId,
                    'status' => self::STATUS_PENDING, 'attempts' => 0,
                ];
                if (count($rows) >= 1000) {
                    $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows, ['status', 'attempts']);
                    $rows = [];
                }
            }
        }
        if ($rows) {
            $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows, ['status', 'attempts']);
        }
    }

    /**
     * Pending (or retryable failed) work, grouped by store
     *
     * @param int $limit
     * @return array<int, int[]> store ID => product IDs
     */
    public function getPending(int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['store_id', 'product_id'])
            ->where('status = ? OR (status = \'' . self::STATUS_FAILED . '\' AND attempts < ' . self::MAX_ATTEMPTS . ')', self::STATUS_PENDING)
            ->order('updated_at ASC')
            ->limit($limit);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['store_id']][] = (int)$row['product_id'];
        }
        return $result;
    }

    /**
     * @param int $storeId
     * @param int[] $productIds
     * @return array<int, array> product ID => row
     */
    public function getRows(int $storeId, array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getMainTable())
            ->where('store_id = ?', $storeId)
            ->where('product_id IN (?)', array_map('intval', $productIds));
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['product_id']] = $row;
        }
        return $result;
    }

    /**
     * Encoded vectors of indexed products
     *
     * @param int $storeId
     * @param int[]|null $productIds null = all indexed products of the store
     * @param string|null $model only vectors of this model (vectors of different models are not comparable)
     * @return array<int, string>
     */
    public function getVectors(int $storeId, ?array $productIds, ?string $model = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getMainTable(), ['product_id', 'vector'])
            ->where('store_id = ?', $storeId)
            ->where('status = ?', self::STATUS_INDEXED)
            ->where('vector IS NOT NULL');
        if ($productIds !== null) {
            if (!$productIds) {
                return [];
            }
            $select->where('product_id IN (?)', array_map('intval', $productIds));
        }
        if ($model !== null) {
            $select->where('model = ?', $model);
        }
        return $connection->fetchPairs($select);
    }

    /**
     * @param array $rows full rows keyed by column
     * @return void
     */
    public function saveRows(array $rows): void
    {
        if (!$rows) {
            return;
        }
        $columns = array_keys(reset($rows));
        $update = array_values(array_diff($columns, ['store_id', 'product_id']));
        foreach (array_chunk($rows, 200) as $chunk) {
            $this->getConnection()->insertOnDuplicate($this->getMainTable(), $chunk, $update);
        }
    }

    /**
     * @param int $storeId
     * @param int[] $productIds
     * @param string $error
     * @return void
     */
    public function markFailed(int $storeId, array $productIds, string $error): void
    {
        if (!$productIds) {
            return;
        }
        $this->getConnection()->update(
            $this->getMainTable(),
            [
                'status' => self::STATUS_FAILED,
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'last_error' => mb_substr($error, 0, 1000),
            ],
            ['store_id = ?' => $storeId, 'product_id IN (?)' => $productIds]
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

    /**
     * Index progress per store: status => count
     *
     * @return array<int, array<string, int>>
     */
    public function getStatusSummary(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['store_id', 'status', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->group(['store_id', 'status']);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['store_id']][(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * Most recent failures for the monitor
     *
     * @param int $limit
     * @return array
     */
    public function getFailures(int $limit = 50): array
    {
        $connection = $this->getConnection();
        return $connection->fetchAll(
            $connection->select()->from($this->getMainTable(), ['store_id', 'product_id', 'sku', 'attempts', 'last_error', 'updated_at'])
                ->where('status = ?', self::STATUS_FAILED)
                ->order('updated_at DESC')
                ->limit($limit)
        );
    }

    /**
     * Reset failed rows so they are retried
     *
     * @return int
     */
    public function retryFailed(): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => self::STATUS_PENDING, 'attempts' => 0],
            ['status = ?' => self::STATUS_FAILED]
        );
    }
}
