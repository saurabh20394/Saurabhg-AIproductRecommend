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
 * Behavior / recommendation event submitted by a storefront or headless client
 *
 * @api
 */
interface EventInterface
{
    /**
     * Client generated unique ID (idempotency key)
     *
     * @return string|null
     */
    public function getEventId(): ?string;

    /**
     * @param string|null $eventId
     * @return $this
     */
    public function setEventId(?string $eventId);

    /**
     * product_view, category_view, search, add_to_cart, remove_from_cart, wishlist_add,
     * recommendation_impression, recommendation_click, recommendation_add_to_cart
     *
     * @return string
     */
    public function getEventType(): string;

    /**
     * @param string $eventType
     * @return $this
     */
    public function setEventType(string $eventType);

    /**
     * Unix timestamp (seconds); defaults to the time of receipt
     *
     * @return int|null
     */
    public function getTimestamp(): ?int;

    /**
     * @param int|null $timestamp
     * @return $this
     */
    public function setTimestamp(?int $timestamp);

    /**
     * @return string|null
     */
    public function getSku(): ?string;

    /**
     * @param string|null $sku
     * @return $this
     */
    public function setSku(?string $sku);

    /**
     * @return int|null
     */
    public function getProductId(): ?int;

    /**
     * @param int|null $productId
     * @return $this
     */
    public function setProductId(?int $productId);

    /**
     * @return int|null
     */
    public function getCategoryId(): ?int;

    /**
     * @param int|null $categoryId
     * @return $this
     */
    public function setCategoryId(?int $categoryId);

    /**
     * Search query (search events)
     *
     * @return string|null
     */
    public function getQuery(): ?string;

    /**
     * @param string|null $query
     * @return $this
     */
    public function setQuery(?string $query);

    /**
     * @return float|null
     */
    public function getQuantity(): ?float;

    /**
     * @param float|null $quantity
     * @return $this
     */
    public function setQuantity(?float $quantity);

    /**
     * Pseudonymous visitor ID (headless clients; storefront uses the first-party cookie)
     *
     * @return string|null
     */
    public function getVisitorId(): ?string;

    /**
     * @param string|null $visitorId
     * @return $this
     */
    public function setVisitorId(?string $visitorId);

    /**
     * Recommendation unit code (recommendation_* events without a tracking token)
     *
     * @return string|null
     */
    public function getUnitCode(): ?string;

    /**
     * @param string|null $unitCode
     * @return $this
     */
    public function setUnitCode(?string $unitCode);

    /**
     * 1-based position in the recommendation unit
     *
     * @return int|null
     */
    public function getPosition(): ?int;

    /**
     * @param int|null $position
     * @return $this
     */
    public function setPosition(?int $position);

    /**
     * Tracking token from the recommendation response
     *
     * @return string|null
     */
    public function getTrackingToken(): ?string;

    /**
     * @param string|null $trackingToken
     * @return $this
     */
    public function setTrackingToken(?string $trackingToken);
}
