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
 * Lightweight bulk catalog lookups (avoid N+1 product loads)
 */
class Catalog extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('catalog_product_entity', 'entity_id');
    }

    /**
     * @param int[] $productIds
     * @return array<int, int[]> product ID => category IDs (direct assignments)
     */
    public function getCategoryIds(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('catalog_category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $productIds);
        $result = array_fill_keys($productIds, []);
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['product_id']][] = (int)$row['category_id'];
        }
        return $result;
    }

    /**
     * @param int[] $productIds
     * @return array<int, string> product ID => SKU
     */
    public function getSkus(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        return $connection->fetchPairs(
            $connection->select()->from($this->getMainTable(), ['entity_id', 'sku'])->where('entity_id IN (?)', $productIds)
        );
    }

    /**
     * @param string[] $skus
     * @return array<string, int> SKU => product ID (case-insensitive input)
     */
    public function getIdsBySkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus), 'strlen')));
        if (!$skus) {
            return [];
        }
        $connection = $this->getConnection();
        $pairs = $connection->fetchPairs(
            $connection->select()->from($this->getMainTable(), ['sku', 'entity_id'])->where('sku IN (?)', $skus)
        );
        return array_map('intval', $pairs);
    }

    /**
     * Products of a website assigned to any of the categories
     *
     * @param int[] $categoryIds
     * @param int $websiteId
     * @param int $limit
     * @param int[] $excludeIds
     * @return int[]
     */
    public function getProductsInCategories(array $categoryIds, int $websiteId, int $limit, array $excludeIds = []): array
    {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds)));
        if (!$categoryIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(['ccp' => $this->getTable('catalog_category_product')], ['product_id'])
            ->join(
                ['pw' => $this->getTable('catalog_product_website')],
                'pw.product_id = ccp.product_id',
                []
            )
            ->where('ccp.category_id IN (?)', $categoryIds)
            ->where('pw.website_id = ?', $websiteId)
            ->order('ccp.product_id DESC')
            ->limit($limit);
        if ($excludeIds) {
            $select->where('ccp.product_id NOT IN (?)', array_map('intval', $excludeIds));
        }
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Newest products of a website, optionally restricted to categories
     *
     * @param int $websiteId
     * @param int $limit
     * @param int[]|null $categoryIds
     * @return array<int, float> product ID => recency score (1 = newest)
     */
    public function getNewest(int $websiteId, int $limit, ?array $categoryIds = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['e' => $this->getMainTable()], ['entity_id'])
            ->join(['pw' => $this->getTable('catalog_product_website')], 'pw.product_id = e.entity_id', [])
            ->where('pw.website_id = ?', $websiteId)
            ->order('e.created_at DESC')
            ->order('e.entity_id DESC')
            ->limit($limit);
        if ($categoryIds) {
            $select->join(['ccp' => $this->getTable('catalog_category_product')], 'ccp.product_id = e.entity_id', [])
                ->where('ccp.category_id IN (?)', array_map('intval', $categoryIds))
                ->group('e.entity_id');
        }
        $ids = array_map('intval', $connection->fetchCol($select));
        $count = count($ids);
        $result = [];
        foreach ($ids as $index => $id) {
            $result[$id] = 1.0 - ($index / max(1, $count)) * 0.5;
        }
        return $result;
    }

    /**
     * Raw EAV values of one product attribute (store value overrides the default value)
     *
     * @param int[] $productIds
     * @param string $attributeCode
     * @param int $storeId
     * @return array<int, string>
     */
    public function getAttributeValues(array $productIds, string $attributeCode, int $storeId): array
    {
        if (!$productIds || !preg_match('/^[a-z][a-z0-9_]*$/', $attributeCode)) {
            return [];
        }
        $connection = $this->getConnection();
        $attribute = $connection->fetchRow(
            $connection->select()
                ->from(['ea' => $this->getTable('eav_attribute')], ['attribute_id', 'backend_type'])
                ->join(['et' => $this->getTable('eav_entity_type')], 'et.entity_type_id = ea.entity_type_id', [])
                ->where('et.entity_type_code = ?', 'catalog_product')
                ->where('ea.attribute_code = ?', $attributeCode)
        );
        if (!$attribute || $attribute['backend_type'] === 'static') {
            return [];
        }
        $linkField = $connection->tableColumnExists($this->getTable('catalog_product_entity'), 'row_id') ? 'row_id' : 'entity_id';
        $select = $connection->select()
            ->from(['v' => $this->getTable('catalog_product_entity_' . $attribute['backend_type'])], ['store_id', 'value'])
            ->join(['e' => $this->getMainTable()], 'e.' . $linkField . ' = v.' . $linkField, ['entity_id'])
            ->where('v.attribute_id = ?', (int)$attribute['attribute_id'])
            ->where('v.store_id IN (?)', [0, $storeId])
            ->where('e.entity_id IN (?)', array_map('intval', $productIds))
            ->order('v.store_id ASC');
        $values = [];
        foreach ($connection->fetchAll($select) as $row) {
            if ($row['value'] !== null && $row['value'] !== '') {
                $values[(int)$row['entity_id']] = (string)$row['value'];
            }
        }
        return $values;
    }

    /**
     * Salability per product without joining the product collection (Magento may already join the stock
     * index in storefront areas). Uses the MSI stock index of the website when MSI is installed, otherwise
     * the legacy stock status table.
     *
     * @param int[] $productIds
     * @param int $websiteId
     * @return array<int, bool>
     */
    public function getSalability(array $productIds, int $websiteId): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $result = array_fill_keys($productIds, false);
        $stockTable = $this->getMsiStockTable($websiteId);
        if ($stockTable !== null) {
            $select = $connection->select()
                ->from(['e' => $this->getMainTable()], ['entity_id'])
                ->join(['s' => $stockTable], 's.sku = e.sku', ['is_salable'])
                ->where('e.entity_id IN (?)', $productIds);
        } else {
            $select = $connection->select()
                ->from($this->getTable('cataloginventory_stock_status'), ['product_id', 'stock_status'])
                ->where('product_id IN (?)', $productIds)
                ->where('website_id = ?', 0);
        }
        foreach ($connection->fetchPairs($select) as $productId => $salable) {
            $result[(int)$productId] = (bool)(int)$salable;
        }
        return $result;
    }

    /**
     * @var array<int, string|null>
     */
    private array $stockTables = [];

    /**
     * @param int $websiteId
     * @return string|null
     */
    private function getMsiStockTable(int $websiteId): ?string
    {
        if (array_key_exists($websiteId, $this->stockTables)) {
            return $this->stockTables[$websiteId];
        }
        $connection = $this->getConnection();
        $channels = $this->getTable('inventory_stock_sales_channel');
        $table = null;
        if ($connection->isTableExists($channels)) {
            $stockId = (int)$connection->fetchOne(
                $connection->select()
                    ->from(['c' => $channels], ['stock_id'])
                    ->join(['w' => $this->getTable('store_website')], 'w.code = c.code', [])
                    ->where('c.type = ?', 'website')
                    ->where('w.website_id = ?', $websiteId)
            ) ?: 1;
            $candidate = $this->getTable('inventory_stock_' . $stockId);
            $table = $connection->isTableExists($candidate) ? $candidate : null;
        }
        return $this->stockTables[$websiteId] = $table;
    }

    /**
     * Stock quantities from the default stock item table
     *
     * @param int[] $productIds
     * @return array<int, float>
     */
    public function getQuantities(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('cataloginventory_stock_item'), ['product_id', 'qty'])
            ->where('product_id IN (?)', array_map('intval', $productIds))
            ->where('stock_id = ?', 1);
        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * Minimal indexed price for the NOT LOGGED IN group
     *
     * @param int[] $productIds
     * @param int $websiteId
     * @return array<int, float>
     */
    public function getIndexedPrices(array $productIds, int $websiteId): array
    {
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('catalog_product_index_price'), ['entity_id', 'min_price'])
            ->where('entity_id IN (?)', array_map('intval', $productIds))
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', 0);
        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * Product IDs updated since a moment (reconciliation)
     *
     * @param string $since
     * @param int $limit
     * @return int[]
     */
    public function getUpdatedSince(string $since, int $limit): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->getMainTable(), ['entity_id'])
                ->where('updated_at >= ?', $since)->order('updated_at ASC')->limit($limit)
        ));
    }

    /**
     * All product IDs assigned to a website
     *
     * @param int $websiteId
     * @return int[]
     */
    public function getWebsiteProductIds(int $websiteId): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->getTable('catalog_product_website'), ['product_id'])
                ->where('website_id = ?', $websiteId)
        ));
    }
}
