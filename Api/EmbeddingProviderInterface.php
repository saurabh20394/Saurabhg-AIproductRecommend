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
 * Embedding provider abstraction; implementations must never receive customer data
 *
 * @api
 */
interface EmbeddingProviderInterface
{
    /**
     * Embed catalog texts
     *
     * @param string[] $texts keyed by any identifier; keys are preserved
     * @param int|null $storeId
     * @return array<int|string, float[]>
     * @throws LocalizedException
     */
    public function embed(array $texts, ?int $storeId = null): array;

    /**
     * Model identifier persisted with each embedding
     *
     * @param int|null $storeId
     * @return string
     */
    public function getModel(?int $storeId = null): string;

    /**
     * Model / algorithm version persisted with each embedding
     *
     * @param int|null $storeId
     * @return string
     */
    public function getModelVersion(?int $storeId = null): string;
}
