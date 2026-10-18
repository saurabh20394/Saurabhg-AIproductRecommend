<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

/**
 * Authorized catalog (re)index operations
 *
 * @api
 */
interface ReindexManagementInterface
{
    /**
     * Queue products for embedding / sync; full reindex when no product IDs are given
     *
     * @param int[]|null $productIds
     * @param int[]|null $storeIds
     * @return int number of queued products
     */
    public function reindex(?array $productIds = null, ?array $storeIds = null): int;
}
