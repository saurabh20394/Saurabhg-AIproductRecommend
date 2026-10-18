<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api\Data;

/**
 * Recommendation unit (widget) configuration
 *
 * @api
 */
interface UnitInterface
{
    public const ENTITY_ID = 'entity_id';
    public const CODE = 'code';
    public const TITLE = 'title';
    public const TYPE = 'type';
    public const STATUS = 'status';
    public const STORE_IDS = 'store_ids';
    public const PLACEMENT = 'placement';
    public const POSITION = 'position';
    public const PRODUCT_COUNT = 'product_count';
    public const MIN_SCORE = 'min_score';
    public const FALLBACK_TYPE = 'fallback_type';
    public const CUSTOMER_GROUP_IDS = 'customer_group_ids';
    public const SEGMENT_IDS = 'segment_ids';
    public const SHOW_REASON = 'show_reason';
    public const PINNED_SKUS = 'pinned_skus';
    public const EXCLUDED_SKUS = 'excluded_skus';
    public const EXCLUDED_CATEGORY_IDS = 'excluded_category_ids';
    public const COMPLEMENTARY_MAP = 'complementary_map';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code);

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title);

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type);

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @return string
     */
    public function getPlacement(): string;

    /**
     * @return int
     */
    public function getProductCount(): int;

    /**
     * @return float
     */
    public function getMinScore(): float;

    /**
     * @return string
     */
    public function getFallbackType(): string;

    /**
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @return int[]
     */
    public function getCustomerGroupIds(): array;

    /**
     * @return int[]
     */
    public function getSegmentIds(): array;

    /**
     * @return bool
     */
    public function isShowReason(): bool;

    /**
     * Pinned SKU => 1-based slot
     *
     * @return array<string, int>
     */
    public function getPinnedSkus(): array;

    /**
     * @return string[]
     */
    public function getExcludedSkus(): array;

    /**
     * @return int[]
     */
    public function getExcludedCategoryIds(): array;

    /**
     * Source category ID (or "*") => complementary category IDs
     *
     * @return array<string, int[]>
     */
    public function getComplementaryMap(): array;
}
