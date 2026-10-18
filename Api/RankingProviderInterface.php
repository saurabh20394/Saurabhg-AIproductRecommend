<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Remote recommendation / ranking provider abstraction
 *
 * @api
 */
interface RankingProviderInterface
{
    /**
     * Request recommendations; the response is validated and treated as untrusted input
     *
     * @param array $request {store_id, visitor_id, customer_id, context: {...}, unit: {code, type, limit}}
     * @param int $storeId
     * @return array{source: string, model_version: string, recommendations: array<int, array{sku: string, score: float, reason_code: string}>}
     * @throws LocalizedException
     */
    public function recommend(array $request, int $storeId): array;
}
