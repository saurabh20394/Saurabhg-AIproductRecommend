<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductDataLoader;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Builds the catalog sync payload and the embedding source sections of products.
 * Product data only: customer data is never part of a payload (no PII by design).
 */
class ProductPayloadBuilder
{
    private const DESCRIPTION_LIMIT = 2000;

    /**
     * @param CollectionFactory $collectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Visibility $visibility
     * @param ProductDataLoader $productDataLoader
     * @param Catalog $catalog
     * @param StoreResolver $storeResolver
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Visibility $visibility,
        private readonly ProductDataLoader $productDataLoader,
        private readonly Catalog $catalog,
        private readonly StoreResolver $storeResolver,
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array{eligible: bool, sku: string, payload: array, sections: array<string, array{0: float, 1: string}>}>
     */
    public function build(array $productIds, int $storeId): array
    {
        if (!$productIds) {
            return [];
        }
        $brandAttribute = $this->config->getBrandAttribute($storeId);
        $extra = array_values(array_filter(
            $this->config->getList('catalog/extra_attributes', $storeId),
            static fn($code) => (bool)preg_match('/^[a-z][a-z0-9_]{0,59}$/', $code)
        ));
        $fields = $this->config->getList('catalog/embedding_fields', $storeId)
            ?: ['name', 'brand', 'categories', 'short_description', 'description'];
        $attributes = $this->productDataLoader->existingAttributes(array_merge(
            ['name', 'status', 'visibility', 'url_key', 'short_description', 'description', 'meta_keyword', $brandAttribute],
            $extra
        ));

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addIdFilter($productIds)
            ->addAttributeToSelect($attributes);

        $websiteId = $this->storeResolver->getWebsiteId($storeId);
        $websiteProducts = $this->getWebsiteProducts($productIds, $websiteId);
        $categoryIds = $this->catalog->getCategoryIds($productIds);
        $categoryNames = $this->getCategoryNames(array_merge([], ...array_values($categoryIds)), $storeId);
        $prices = $this->catalog->getIndexedPrices($productIds, $websiteId);
        $salable = $this->catalog->getSalability($productIds, $websiteId);
        $quantities = $this->config->isSetFlag('catalog/sync_qty', $storeId) ? $this->catalog->getQuantities($productIds) : [];
        $superAttributes = $this->getConfigurableAttributeCodes($productIds);
        $visible = $this->visibility->getVisibleInSiteIds();

        $result = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $id = (int)$product->getId();
            $names = array_values(array_filter(array_map(
                static fn($cid) => $categoryNames[$cid] ?? null,
                $categoryIds[$id] ?? []
            )));
            $brand = $brandAttribute !== '' ? $this->getOptionText($product, $brandAttribute) : null;
            $attributeValues = [];
            foreach ($extra as $code) {
                $text = $this->getOptionText($product, $code);
                if ($text !== null && $text !== '') {
                    $attributeValues[$code] = $text;
                }
            }
            $eligible = (int)$product->getData('status') === Status::STATUS_ENABLED
                && in_array((int)$product->getData('visibility'), $visible, true)
                && isset($websiteProducts[$id]);

            $payload = [
                'id' => $id,
                'sku' => (string)$product->getSku(),
                'type' => (string)$product->getTypeId(),
                'name' => (string)$product->getName(),
                'short_description' => $this->clean((string)$product->getData('short_description'), 500),
                'description' => $this->clean((string)$product->getData('description'), self::DESCRIPTION_LIMIT),
                'category_ids' => $categoryIds[$id] ?? [],
                'categories' => $names,
                'brand' => $brand,
                'price' => $prices[$id] ?? null,
                'status' => (int)$product->getData('status') === Status::STATUS_ENABLED ? 'enabled' : 'disabled',
                'visibility' => (int)$product->getData('visibility'),
                'is_salable' => $salable[$id] ?? false,
                'eligible' => $eligible,
                'attributes' => $attributeValues,
                'configurable_attributes' => $superAttributes[$id] ?? [],
            ];
            if ($quantities) {
                $payload['qty'] = $quantities[$id] ?? null;
            }

            $sections = [];
            foreach ($fields as $field) {
                $value = match ($field) {
                    'name' => $payload['name'],
                    'brand' => (string)$brand,
                    'categories' => implode(', ', $names),
                    'short_description' => $payload['short_description'],
                    'description' => $payload['description'],
                    'meta_keyword' => $this->clean((string)$product->getData('meta_keyword'), 300),
                    default => '',
                };
                if ($value !== '') {
                    $sections[$field] = [match ($field) {
                        'name' => 3.0, 'brand' => 2.0, 'categories' => 2.0, default => 1.0,
                    }, $value];
                }
            }
            if ($attributeValues) {
                $sections['attributes'] = [1.5, implode(', ', array_map(
                    static fn($code, $value) => $code . ' ' . $value,
                    array_keys($attributeValues),
                    $attributeValues
                ))];
            }
            $result[$id] = ['eligible' => $eligible, 'sku' => $payload['sku'], 'payload' => $payload, 'sections' => $sections];
        }
        return $result;
    }

    /**
     * Embedding input text: weighted lines for the local provider, labeled plain text for remote models
     *
     * @param array<string, array{0: float, 1: string}> $sections
     * @param bool $weighted
     * @return string
     */
    public function toText(array $sections, bool $weighted): string
    {
        $lines = [];
        foreach ($sections as $field => [$weight, $value]) {
            $lines[] = $weighted
                ? sprintf('%s^%s: %s', $field, rtrim(rtrim(number_format($weight, 1, '.', ''), '0'), '.'), $value)
                : ucfirst(str_replace('_', ' ', $field)) . ': ' . $value;
        }
        return implode("\n", $lines);
    }

    /**
     * @param string $value
     * @param int $limit
     * @return string
     */
    private function clean(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $value) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string)preg_replace('/\s+/u', ' ', $value));
        // template directives such as {{widget ...}} are not product content
        $value = trim((string)preg_replace('/\{\{.*?\}\}/s', ' ', $value));
        return mb_substr($value, 0, $limit);
    }

    /**
     * @param Product $product
     * @param string $code
     * @return string|null
     */
    private function getOptionText(Product $product, string $code): ?string
    {
        $raw = $product->getData($code);
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            $attribute = $product->getResource()->getAttribute($code);
            if ($attribute && $attribute->usesSource()) {
                $text = $attribute->getSource()->getOptionText($raw);
                if (is_array($text)) {
                    $text = implode(', ', $text);
                }
                return $text !== false && $text !== null ? (string)$text : null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return is_scalar($raw) ? $this->clean((string)$raw, 200) : null;
    }

    /**
     * @param int[] $categoryIds
     * @param int $storeId
     * @return array<int, string>
     */
    private function getCategoryNames(array $categoryIds, int $storeId): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (!$categoryIds) {
            return [];
        }
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIdFilter($categoryIds)
            ->addAttributeToFilter('level', ['gt' => 1]);
        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = (string)$category->getName();
        }
        return $names;
    }

    /**
     * @param int[] $productIds
     * @param int $websiteId
     * @return array<int, bool>
     */
    private function getWebsiteProducts(array $productIds, int $websiteId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $ids = $connection->fetchCol(
            $connection->select()->from($this->resourceConnection->getTableName('catalog_product_website'), ['product_id'])
                ->where('website_id = ?', $websiteId)
                ->where('product_id IN (?)', array_map('intval', $productIds))
        );
        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * @param int[] $productIds
     * @return array<int, string[]>
     */
    private function getConfigurableAttributeCodes(array $productIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_super_attribute');
        if (!$connection->isTableExists($table)) {
            return [];
        }
        $linkField = $connection->tableColumnExists($this->resourceConnection->getTableName('catalog_product_entity'), 'row_id')
            ? 'row_id' : 'entity_id';
        $select = $connection->select()
            ->from(['sa' => $table], [])
            ->join(['e' => $this->resourceConnection->getTableName('catalog_product_entity')], 'e.' . $linkField . ' = sa.product_id', ['entity_id'])
            ->join(['ea' => $this->resourceConnection->getTableName('eav_attribute')], 'ea.attribute_id = sa.attribute_id', ['attribute_code'])
            ->where('e.entity_id IN (?)', array_map('intval', $productIds));
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['entity_id']][] = (string)$row['attribute_code'];
        }
        return $result;
    }
}
