<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;

/**
 * Loads the facts Magento is the source of truth for: status, visibility, website, salability,
 * price, stock, categories and brand. Products missing from the result are not eligible.
 */
class ProductDataLoader
{
    public const EXCLUDE_ATTRIBUTE = 'ai_recommend_exclude';

    private const QTY_TYPES = ['simple', 'virtual', 'downloadable'];

    /**
     * @var array<string, bool>
     */
    private array $attributeExists = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param Visibility $visibility
     * @param EavConfig $eavConfig
     * @param Catalog $catalog
     * @param Config $config
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Visibility $visibility,
        private readonly EavConfig $eavConfig,
        private readonly Catalog $catalog,
        private readonly Config $config
    ) {
    }

    /**
     * @param int[] $productIds
     * @param Context $context
     * @param string[] $extraAttributes attribute codes required by rules
     * @return array<int, array> product ID => facts
     */
    public function load(array $productIds, Context $context, array $extraAttributes = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }
        $storeId = $context->storeId;
        $brandAttribute = $this->config->getBrandAttribute($storeId);
        $attributes = $this->existingAttributes(array_merge(
            ['special_price', self::EXCLUDE_ATTRIBUTE, $brandAttribute],
            $extraAttributes
        ));

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addIdFilter($productIds)
            ->addAttributeToSelect($attributes)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSiteIds()]);
        $collection->addPriceData($context->customerGroupId, $context->websiteId);

        $categories = $this->catalog->getCategoryIds($productIds);
        $quantities = $this->catalog->getQuantities($productIds);
        $salable = $this->catalog->getSalability($productIds, $context->websiteId);
        $lowStock = $this->config->getFloat('inventory/low_stock_threshold', $storeId, 5.0);

        $result = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $id = (int)$product->getId();
            $type = (string)$product->getTypeId();
            $qty = in_array($type, self::QTY_TYPES, true) && isset($quantities[$id]) ? $quantities[$id] : null;
            $regular = (float)$product->getData('price');
            $final = (float)($product->getData('final_price') ?? $product->getData('min_price') ?? 0);
            $brandValue = $brandAttribute !== '' ? $product->getData($brandAttribute) : null;
            $extra = [];
            foreach ($extraAttributes as $code) {
                $extra[$code] = $product->getData($code);
            }
            $result[$id] = [
                'id' => $id,
                'sku' => (string)$product->getSku(),
                'type' => $type,
                'categories' => $categories[$id] ?? [],
                'brand' => $brandValue !== null && $brandValue !== '' ? (string)$brandValue : null,
                'brand_label' => $this->getBrandLabel($product, $brandAttribute),
                'price' => $final > 0 ? $final : (float)$product->getData('min_price'),
                'on_sale' => $regular > 0 && $final > 0 && $final < $regular,
                'in_stock' => $salable[$id] ?? false,
                'qty' => $qty,
                'low_stock' => $qty !== null && $qty <= $lowStock,
                'created_at' => (string)$product->getData('created_at'),
                'excluded_flag' => (bool)(int)$product->getData(self::EXCLUDE_ATTRIBUTE),
                'attributes' => $extra,
            ];
        }
        return $result;
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    public function existingAttributes(array $codes): array
    {
        $existing = [];
        foreach (array_unique(array_filter($codes)) as $code) {
            if (!isset($this->attributeExists[$code])) {
                try {
                    $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
                    $this->attributeExists[$code] = (bool)($attribute && $attribute->getId());
                } catch (\Throwable $e) {
                    $this->attributeExists[$code] = false;
                }
            }
            if ($this->attributeExists[$code]) {
                $existing[] = $code;
            }
        }
        return $existing;
    }

    /**
     * @param Product $product
     * @param string $brandAttribute
     * @return string|null
     */
    private function getBrandLabel(Product $product, string $brandAttribute): ?string
    {
        if ($brandAttribute === '' || $product->getData($brandAttribute) === null) {
            return null;
        }
        try {
            $resource = $product->getResource()->getAttribute($brandAttribute);
            if ($resource && $resource->usesSource()) {
                $label = $resource->getSource()->getOptionText($product->getData($brandAttribute));
                return is_string($label) ? $label : null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return (string)$product->getData($brandAttribute);
    }
}
