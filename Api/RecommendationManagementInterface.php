<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

use Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface;

/**
 * Recommendation retrieval for headless / API clients. Store scope comes from the request URL
 * (/rest/{store_code}/V1/...); customer personalization requires a customer token.
 *
 * @api
 */
interface RecommendationManagementInterface
{
    /**
     * Recommendations for a context
     *
     * @param string|null $unitCode
     * @param string|null $type used when no unit code is given
     * @param string|null $productSku
     * @param int|null $categoryId
     * @param string[]|null $cartSkus
     * @param string|null $visitorId pseudonymous visitor ID (consent is the client's responsibility)
     * @param string|null $pageType
     * @param int|null $limit 1..50
     * @param int|null $customerGroupId honored for admin / integration tokens only
     * @return \Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRecommendations(
        ?string $unitCode = null,
        ?string $type = null,
        ?string $productSku = null,
        ?int $categoryId = null,
        ?array $cartSkus = null,
        ?string $visitorId = null,
        ?string $pageType = null,
        ?int $limit = null,
        ?int $customerGroupId = null
    ): RecommendationResultInterface;

    /**
     * Recommendations of a configured unit
     *
     * @param string $code
     * @param string|null $productSku
     * @param int|null $categoryId
     * @param string[]|null $cartSkus
     * @param string|null $visitorId
     * @param int|null $limit
     * @return \Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUnit(
        string $code,
        ?string $productSku = null,
        ?int $categoryId = null,
        ?array $cartSkus = null,
        ?string $visitorId = null,
        ?int $limit = null
    ): RecommendationResultInterface;

    /**
     * Semantic similar products
     *
     * @param string $sku
     * @param int|null $limit
     * @return \Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSimilar(string $sku, ?int $limit = null): RecommendationResultInterface;

    /**
     * Trending products
     *
     * @param int|null $limit
     * @param int|null $categoryId
     * @return \Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTrending(?int $limit = null, ?int $categoryId = null): RecommendationResultInterface;
}
