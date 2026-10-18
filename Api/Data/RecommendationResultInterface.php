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
 * Recommendation response for one unit / context
 *
 * @api
 */
interface RecommendationResultInterface
{
    /**
     * @return string|null
     */
    public function getUnitCode(): ?string;

    /**
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * Recommendation type actually served
     *
     * @return string
     */
    public function getType(): string;

    /**
     * local, remote, cache, last_good, fallback, holdout, disabled, not_applicable
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * @return string|null
     */
    public function getModelVersion(): ?string;

    /**
     * True when the primary engine was unavailable or empty and a fallback answered
     *
     * @return bool
     */
    public function getIsFallback(): bool;

    /**
     * Correlation ID of this response
     *
     * @return string
     */
    public function getRequestId(): string;

    /**
     * Opaque signed token to send back with impression / click events
     *
     * @return string|null
     */
    public function getTrackingToken(): ?string;

    /**
     * @return \Saurabhg\AIproductRecommend\Api\Data\RecommendationItemInterface[]
     */
    public function getItems(): array;
}
