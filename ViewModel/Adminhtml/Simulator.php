<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\ViewModel\Adminhtml;

use Magento\Framework\App\Area;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\Recommendation\ContextBuilder;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductHydrator;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;
use Saurabhg\AIproductRecommend\Model\Source\CustomerGroup;
use Saurabhg\AIproductRecommend\Model\Source\UnitCode;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Rule simulation / preview and merchant explainability: runs the live pipeline for a sample context
 * without cache and shows how each product's score is built, applied rules / campaigns and exclusions
 */
class Simulator implements ArgumentInterface
{
    private const FIELDS = [
        'store_id', 'unit_code', 'type', 'page_type', 'product_sku', 'category_id', 'cart_skus',
        'customer_id', 'visitor_id', 'customer_group_id', 'limit',
    ];

    private const FEATURES = [
        'similarity' => ['Similarity', 'How alike the product is to the current product (AI embeddings)'],
        'behavior' => ['Bought / viewed together', 'Co-purchase and co-view strength with the page context'],
        'affinity' => ['Shopper affinity', 'Match with the shopper\'s favourite categories, brands and price range'],
        'popularity' => ['Popularity', 'Views, carts and orders in the popularity window'],
        'trending' => ['Trending', 'Recent, time-decayed demand'],
        'conversion' => ['Conversion', 'Purchases per view'],
        'inventory' => ['Inventory', 'In stock (full) or low stock (half)'],
        'recency' => ['Recently viewed', 'How recently the shopper viewed it'],
        'newness' => ['New arrival', 'How recently the product was created'],
        'ai' => ['Remote AI score', 'Score returned by the external recommendation API'],
    ];

    private const PAGE_TYPES = [
        'product' => 'Product page', 'category' => 'Category page', 'home' => 'Homepage',
        'cart' => 'Shopping cart', 'success' => 'Order success', 'search' => 'Search results', 'api' => 'API / headless',
    ];

    /**
     * @param RequestInterface $request
     * @param ContextBuilder $contextBuilder
     * @param RecommendationService $service
     * @param ProductHydrator $hydrator
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     * @param UnitCode $unitCode
     * @param UnitType $unitType
     * @param CustomerGroup $customerGroup
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ContextBuilder $contextBuilder,
        private readonly RecommendationService $service,
        private readonly ProductHydrator $hydrator,
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager,
        private readonly UnitCode $unitCode,
        private readonly UnitType $unitType,
        private readonly CustomerGroup $customerGroup,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array
     */
    public function getInput(): array
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = trim((string)$this->request->getParam($field, ''));
        }
        return $input;
    }

    /**
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return (bool)$this->request->getParam('simulate');
    }

    /**
     * @return array{result: ?\Saurabhg\AIproductRecommend\Model\Data\RecommendationResult, error: ?string,
     *               context: ?Context, items: array[], excluded: array[], debug: array, store_id: int}
     */
    public function simulate(): array
    {
        $input = $this->getInput();
        $storeId = (int)$input['store_id'] ?: (int)$this->storeManager->getDefaultStoreView()->getId();
        $params = [
            'store_id' => $storeId,
            'unit_code' => $input['unit_code'] ?: null,
            'type' => $input['unit_code'] ? null : ($input['type'] ?: UnitType::HYBRID),
            'page_type' => $input['page_type'] ?: 'product',
            'product_sku' => $input['product_sku'] ?: null,
            'category_id' => $input['category_id'] ?: null,
            'cart_skus' => $input['cart_skus'] !== '' ? preg_split('/[\s,]+/', $input['cart_skus']) : [],
            'customer_id' => $input['customer_id'] ?: null,
            'visitor_id' => $input['visitor_id'] ?: null,
            'limit' => $input['limit'] ?: null,
            'debug' => true,
            'use_cache' => false,
        ];
        if ($input['customer_group_id'] !== '') {
            $params['customer_group_id'] = (int)$input['customer_group_id'];
        }
        $empty = ['result' => null, 'error' => null, 'context' => null, 'items' => [], 'excluded' => [], 'debug' => [], 'store_id' => $storeId];
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $context = $this->contextBuilder->create(array_filter($params, static fn($v) => $v !== null), Context::CHANNEL_ADMIN);
            $result = $this->service->recommend($context);
            $debug = (array)$result->getData('debug');
            $weights = (array)($debug['weights'] ?? []);
            $excludedIds = array_map('intval', array_keys((array)($debug['excluded'] ?? [])));
            $itemIds = array_map(static fn($item) => $item->getProductId(), $result->getItems());
            $products = $this->describeProducts(array_merge($itemIds, $excludedIds), $storeId);

            $items = [];
            $maxScore = 0.0001;
            foreach ($result->getItems() as $item) {
                $maxScore = max($maxScore, (float)$item->getData('final'));
            }
            foreach ($result->getItems() as $item) {
                $features = (array)$item->getData('features');
                $parts = [];
                foreach ($weights as $feature => $weight) {
                    $value = (float)$weight * (float)($features[$feature] ?? 0);
                    if ($value > 0) {
                        $parts[] = [
                            'feature' => $feature,
                            'label' => self::FEATURES[$feature][0] ?? $feature,
                            'value' => round($value, 4),
                            'raw' => (float)($features[$feature] ?? 0),
                            'weight' => (float)$weight,
                            'width' => round($value / $maxScore * 100, 2),
                        ];
                    }
                }
                usort($parts, static fn($a, $b) => $b['value'] <=> $a['value']);
                $boost = (float)$item->getData('boost');
                $items[] = [
                    'position' => $item->getPosition(),
                    'sku' => $item->getSku(),
                    'product' => $products[$item->getProductId()] ?? null,
                    'final' => round((float)$item->getData('final'), 4),
                    'parts' => $parts,
                    'boost' => $boost,
                    'boost_width' => $boost > 0 ? round($boost / $maxScore * 100, 2) : 0,
                    'pinned' => (bool)$item->getData('pinned'),
                    'reason' => $item->getReasonLabel(),
                ];
            }
            $excluded = [];
            foreach ((array)($debug['excluded'] ?? []) as $productId => $reason) {
                $excluded[] = ['product' => $products[(int)$productId] ?? null, 'id' => (int)$productId, 'reason' => (string)$reason];
            }
            return ['result' => $result, 'error' => null, 'context' => $context, 'items' => $items,
                'excluded' => $excluded, 'debug' => $debug, 'store_id' => $storeId];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()] + $empty;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}> feature => [label, description]
     */
    public function getFeatures(): array
    {
        return array_map(static fn($f) => [(string)__($f[0]), (string)__($f[1])], self::FEATURES);
    }

    /**
     * @param string $feature
     * @return string
     */
    public function getFeatureLabel(string $feature): string
    {
        return isset(self::FEATURES[$feature]) ? (string)__(self::FEATURES[$feature][0]) : $feature;
    }

    /**
     * @return array<string, string>
     */
    public function getPageTypes(): array
    {
        return array_map(static fn($label) => (string)__($label), self::PAGE_TYPES);
    }

    /**
     * @return array
     */
    public function getUnitOptions(): array
    {
        return $this->unitCode->toOptionArray();
    }

    /**
     * @return array
     */
    public function getTypeOptions(): array
    {
        return $this->unitType->toOptionArray();
    }

    /**
     * @return array
     */
    public function getGroupOptions(): array
    {
        return $this->customerGroup->toOptionArray();
    }

    /**
     * @return array<int, string>
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int)$store->getId()] = $store->getWebsite()->getName() . ' / ' . $store->getName();
        }
        return $stores;
    }

    /**
     * @return string
     */
    public function getFormUrl(): string
    {
        return $this->urlBuilder->getUrl('*/*/index');
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array{name: string, sku: string, image: ?string, edit_url: string}>
     */
    private function describeProducts(array $productIds, int $storeId): array
    {
        $media = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        $result = [];
        foreach ($this->hydrator->loadProducts(array_values(array_unique($productIds)), $storeId) as $id => $product) {
            $image = (string)($product->getData('small_image') ?: $product->getData('thumbnail'));
            $result[$id] = [
                'name' => (string)$product->getName(),
                'sku' => (string)$product->getSku(),
                'image' => $image && $image !== 'no_selection' ? $media . '/catalog/product' . $image : null,
                'edit_url' => $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $id]),
            ];
        }
        return $result;
    }
}
