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
 * Recommended product
 *
 * @api
 */
interface RecommendationItemInterface
{
    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @return int
     */
    public function getProductId(): int;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Canonical product URL
     *
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * Final price in the store currency
     *
     * @return float|null
     */
    public function getPrice(): ?float;

    /**
     * @return string|null
     */
    public function getImageUrl(): ?string;

    /**
     * 1-based slot
     *
     * @return int
     */
    public function getPosition(): int;

    /**
     * Relevance score 0..1; null unless exposure is enabled in configuration
     *
     * @return float|null
     */
    public function getScore(): ?float;

    /**
     * Safe, shopper-facing reason code
     *
     * @return string
     */
    public function getReasonCode(): string;

    /**
     * Translated reason label
     *
     * @return string
     */
    public function getReasonLabel(): string;
}
