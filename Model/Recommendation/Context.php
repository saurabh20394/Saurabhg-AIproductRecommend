<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

/**
 * Normalized, validated recommendation request context
 */
class Context
{
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_REST = 'api';
    public const CHANNEL_GRAPHQL = 'graphql';
    public const CHANNEL_ADMIN = 'admin';

    public int $storeId = 0;
    public int $websiteId = 0;
    public string $channel = self::CHANNEL_WEB;
    public string $pageType = 'api';
    public ?string $unitCode = null;
    public ?string $type = null;
    public ?int $limit = null;

    public ?int $productId = null;
    public ?string $productSku = null;
    /** @var int[] categories of the current product */
    public array $productCategoryIds = [];
    public ?int $categoryId = null;
    /** @var int[] */
    public array $cartProductIds = [];
    /** @var string[] */
    public array $cartSkus = [];
    /** @var int[] products of the order just placed (success page) */
    public array $orderProductIds = [];
    public ?string $searchQuery = null;

    public ?int $customerId = null;
    public int $customerGroupId = 0;
    public ?string $visitorId = null;
    /** @var int[] */
    public array $segmentIds = [];
    /** @var array derived profile features */
    public array $profile = [];
    public string $profileType = 'anonymous';

    public ?int $experimentId = null;
    public ?int $variantId = null;
    public ?string $variantCode = null;
    /** @var array variant overrides: type, product_count, weights, disabled */
    public array $variantConfig = [];

    public bool $debug = false;
    public bool $useCache = true;

    /**
     * Products that describe the page context and must not be recommended back
     *
     * @return int[]
     */
    public function getContextProductIds(): array
    {
        $ids = array_merge($this->cartProductIds, $this->orderProductIds);
        if ($this->productId) {
            $ids[] = $this->productId;
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Subject key for experiment assignment (customer preferred over visitor)
     *
     * @return string|null
     */
    public function getSubjectKey(): ?string
    {
        if ($this->customerId) {
            return 'c:' . $this->customerId;
        }
        return $this->visitorId ? 'v:' . $this->visitorId : null;
    }

    /**
     * @return bool
     */
    public function hasProfile(): bool
    {
        return (int)($this->profile['events'] ?? 0) > 0 || !empty($this->profile['recent']);
    }
}
