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
 * Aggregate recommendation profile of a customer (no raw behavioral events)
 *
 * @api
 */
interface ProfileInterface
{
    /**
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * anonymous / guest / customer
     *
     * @return string
     */
    public function getProfileType(): string;

    /**
     * @return int
     */
    public function getEventCount(): int;

    /**
     * Strongest category affinities first
     *
     * @return int[]
     */
    public function getTopCategoryIds(): array;

    /**
     * @return string[]
     */
    public function getTopBrands(): array;

    /**
     * @return float|null
     */
    public function getAverageViewedPrice(): ?float;

    /**
     * @return int
     */
    public function getOrdersCount(): int;

    /**
     * @return string[]
     */
    public function getRecentSkus(): array;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
