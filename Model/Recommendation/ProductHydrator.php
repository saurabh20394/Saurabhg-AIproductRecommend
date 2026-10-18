<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface;
use Saurabhg\AIproductRecommend\Model\Data\RecommendationResult;

/**
 * Loads display data for recommended product IDs in one collection query, preserving rank order
 */
class ProductHydrator
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param CatalogConfig $catalogConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CatalogConfig $catalogConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int[] $productIds ordered
     * @param int $storeId
     * @param bool $allAttributes GraphQL needs every attribute for ProductInterface resolution
     * @return array<int, Product> ordered
     */
    public function loadProducts(array $productIds, int $storeId, bool $allAttributes = false): array
    {
        if (!$productIds) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addIdFilter($productIds)
            ->addAttributeToSelect($allAttributes ? '*' : $this->catalogConfig->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();
        $loaded = [];
        foreach ($collection as $product) {
            $loaded[(int)$product->getId()] = $product;
        }
        $ordered = [];
        foreach ($productIds as $productId) {
            if (isset($loaded[(int)$productId])) {
                $ordered[(int)$productId] = $loaded[(int)$productId];
            }
        }
        return $ordered;
    }

    /**
     * Add name, URL, price and image URL to the result items (REST); drops items that cannot be loaded
     *
     * @param RecommendationResultInterface $result
     * @param int $storeId
     * @return array<int, Product> loaded products
     */
    public function hydrate(RecommendationResultInterface $result, int $storeId): array
    {
        $items = $result->getItems();
        $products = $this->loadProducts(array_map(static fn($item) => $item->getProductId(), $items), $storeId);
        $mediaUrl = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        $kept = [];
        foreach ($items as $item) {
            $product = $products[$item->getProductId()] ?? null;
            if (!$product) {
                continue;
            }
            $image = (string)($product->getData('small_image') ?: $product->getData('image'));
            $item->setData('name', (string)$product->getName());
            $item->setData('url', $product->getProductUrl());
            $item->setData('price', $this->getFinalPrice($product));
            $item->setData('image_url', $image && $image !== 'no_selection' ? $mediaUrl . '/catalog/product' . $image : null);
            $item->setData('position', count($kept) + 1);
            $item->setData('product', $product);
            $kept[] = $item;
        }
        if ($result instanceof RecommendationResult) {
            $result->setData('items', $kept);
        }
        return $products;
    }

    /**
     * @param Product $product
     * @return float|null
     */
    private function getFinalPrice(Product $product): ?float
    {
        try {
            return round((float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(), 4);
        } catch (\Throwable $e) {
            return $product->getData('final_price') !== null ? (float)$product->getData('final_price') : null;
        }
    }
}
