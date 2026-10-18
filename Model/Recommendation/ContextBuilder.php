<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Personalization\SegmentResolver;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Builds a validated Context from storefront sessions or API parameters
 */
class ContextBuilder
{
    public const PAGE_TYPES = ['home', 'category', 'product', 'cart', 'success', 'search', 'account', 'api', 'other'];

    private const MAX_CART_ITEMS = 50;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Catalog $catalog
     * @param ProfileService $profileService
     * @param SegmentResolver $segmentResolver
     * @param VisitorIdentity $visitorIdentity
     * @param CustomerRepositoryInterface $customerRepository
     * @param CheckoutSession $checkoutSession
     * @param UnitType $unitType
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Catalog $catalog,
        private readonly ProfileService $profileService,
        private readonly SegmentResolver $segmentResolver,
        private readonly VisitorIdentity $visitorIdentity,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly UnitType $unitType
    ) {
    }

    /**
     * @param array $params unit_code, type, page_type, product_id|product_sku, category_id, cart_skus|cart_product_ids,
     *                      order_product_ids, visitor_id, limit; trusted callers only: customer_id, customer_group_id
     * @param string $channel
     * @return Context
     * @throws LocalizedException on invalid input
     */
    public function create(array $params, string $channel): Context
    {
        $context = new Context();
        $context->channel = $channel;
        $store = $this->storeManager->getStore(isset($params['store_id']) ? (int)$params['store_id'] : null);
        $context->storeId = (int)$store->getId();
        $context->websiteId = (int)$store->getWebsiteId();

        if (!empty($params['unit_code'])) {
            $code = (string)$params['unit_code'];
            if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $code)) {
                throw new LocalizedException(__('Invalid recommendation unit code.'));
            }
            $context->unitCode = $code;
        }
        if (!empty($params['type'])) {
            if (!array_key_exists((string)$params['type'], $this->unitType->getOptions())) {
                throw new LocalizedException(__('Invalid recommendation type.'));
            }
            $context->type = (string)$params['type'];
        }
        $pageType = (string)($params['page_type'] ?? ($channel === Context::CHANNEL_WEB ? 'other' : 'api'));
        $context->pageType = in_array($pageType, self::PAGE_TYPES, true) ? $pageType : 'other';
        if (isset($params['limit']) && $params['limit'] !== '' && $params['limit'] !== null) {
            $limit = (int)$params['limit'];
            if ($limit < 1 || $limit > 50) {
                throw new LocalizedException(__('Limit must be between 1 and 50.'));
            }
            $context->limit = $limit;
        }

        $this->applyProduct($context, $params);
        if (!empty($params['category_id'])) {
            $context->categoryId = max(0, (int)$params['category_id']) ?: null;
        }
        $this->applyCart($context, $params);
        if (!empty($params['order_product_ids'])) {
            $context->orderProductIds = array_slice(array_map('intval', (array)$params['order_product_ids']), 0, self::MAX_CART_ITEMS);
        }

        if (!empty($params['visitor_id'])) {
            if (!VisitorIdentity::isValid((string)$params['visitor_id'])) {
                throw new LocalizedException(__('Invalid visitor ID.'));
            }
            $context->visitorId = (string)$params['visitor_id'];
        }
        $context->customerId = !empty($params['customer_id']) ? (int)$params['customer_id'] : null;
        $context->customerGroupId = isset($params['customer_group_id'])
            ? (int)$params['customer_group_id']
            : $this->getCustomerGroupId($context->customerId);

        $context->profile = $this->profileService->getFeatures($context->customerId, $context->visitorId);
        $context->profileType = (string)($context->profile['profile_type'] ?? 'anonymous');
        $context->segmentIds = $this->segmentResolver->resolve($context);
        $context->debug = !empty($params['debug']);
        $context->useCache = !array_key_exists('use_cache', $params) || (bool)$params['use_cache'];
        return $context;
    }

    /**
     * Storefront request: identity and consent from the session / cookies, cart and order from the checkout session
     *
     * @param array $params raw request parameters (product_id, category_id, page_type)
     * @return Context
     * @throws LocalizedException
     */
    public function fromStorefront(array $params): Context
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $pageType = (string)($params['page_type'] ?? 'other');
        $clean = [
            'store_id' => $storeId,
            'page_type' => $pageType,
            'product_id' => isset($params['product_id']) ? (int)$params['product_id'] : null,
            'category_id' => isset($params['category_id']) ? (int)$params['category_id'] : null,
            'visitor_id' => $this->visitorIdentity->getVisitorId($storeId),
            'customer_id' => $this->visitorIdentity->getCustomerId($storeId),
            'customer_group_id' => $this->visitorIdentity->getCustomerGroupId(),
        ];
        if ($pageType === 'cart') {
            $clean['cart_product_ids'] = $this->getCartProductIds();
        }
        if ($pageType === 'success') {
            $clean['order_product_ids'] = $this->getLastOrderProductIds();
        }
        return $this->create($clean, Context::CHANNEL_WEB);
    }

    /**
     * @param Context $context
     * @param array $params
     * @return void
     */
    private function applyProduct(Context $context, array $params): void
    {
        $productId = !empty($params['product_id']) ? (int)$params['product_id'] : null;
        $sku = isset($params['product_sku']) ? trim((string)$params['product_sku']) : '';
        if (!$productId && $sku !== '') {
            if (strlen($sku) > 64) {
                throw new LocalizedException(__('Invalid product SKU.'));
            }
            $ids = array_change_key_case($this->catalog->getIdsBySkus([$sku]), CASE_LOWER);
            $productId = $ids[strtolower($sku)] ?? null;
        }
        if ($productId) {
            $skus = $this->catalog->getSkus([$productId]);
            if (isset($skus[$productId])) {
                $context->productId = $productId;
                $context->productSku = (string)$skus[$productId];
                $context->productCategoryIds = $this->catalog->getCategoryIds([$productId])[$productId] ?? [];
            }
        }
    }

    /**
     * @param Context $context
     * @param array $params
     * @return void
     */
    private function applyCart(Context $context, array $params): void
    {
        $ids = array_map('intval', (array)($params['cart_product_ids'] ?? []));
        $skus = array_slice(array_map('strval', (array)($params['cart_skus'] ?? [])), 0, self::MAX_CART_ITEMS);
        if ($skus) {
            $ids = array_merge($ids, array_values($this->catalog->getIdsBySkus($skus)));
        }
        $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, self::MAX_CART_ITEMS);
        if ($ids) {
            $map = $this->catalog->getSkus($ids);
            $context->cartProductIds = array_map('intval', array_keys($map));
            $context->cartSkus = array_values($map);
        }
    }

    /**
     * @param int|null $customerId
     * @return int
     */
    private function getCustomerGroupId(?int $customerId): int
    {
        if (!$customerId) {
            return Group::NOT_LOGGED_IN_ID;
        }
        try {
            return (int)$this->customerRepository->getById($customerId)->getGroupId();
        } catch (\Throwable $e) {
            return Group::NOT_LOGGED_IN_ID;
        }
    }

    /**
     * @return int[]
     */
    private function getCartProductIds(): array
    {
        try {
            if (!$this->checkoutSession->getQuoteId()) {
                return [];
            }
            $ids = [];
            foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
                $ids[] = (int)$item->getProductId();
            }
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return int[]
     */
    private function getLastOrderProductIds(): array
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                return [];
            }
            $ids = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $ids[] = (int)$item->getProductId();
            }
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
