<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Saurabhg\AIproductRecommend\Api\Data\UnitInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Global, unit-level and attribute-level exclusions plus the out-of-stock policy
 */
class EligibilityFilter
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param array<int, array> $facts products returned by the loader (status/visibility/website already enforced)
     * @param Context $context
     * @param UnitInterface|null $unit
     * @return array{eligible: array<int, array>, excluded: array<int, string>}
     */
    public function filter(array $facts, Context $context, ?UnitInterface $unit): array
    {
        $storeId = $context->storeId;
        $excludeOutOfStock = $this->config->isSetFlag('inventory/exclude_out_of_stock', $storeId);
        $skus = array_map('strtolower', array_merge(
            $this->config->getList('inventory/excluded_skus', $storeId),
            $unit ? $unit->getExcludedSkus() : []
        ));
        $categories = array_merge(
            array_map('intval', array_filter($this->config->getList('inventory/excluded_categories', $storeId), 'is_numeric')),
            $unit ? $unit->getExcludedCategoryIds() : []
        );
        $types = $this->config->getList('inventory/excluded_types', $storeId);

        $eligible = [];
        $excluded = [];
        foreach ($facts as $productId => $product) {
            $reason = match (true) {
                $product['excluded_flag'] => 'product attribute: excluded from AI recommendations',
                in_array(strtolower($product['sku']), $skus, true) => 'excluded SKU',
                (bool)array_intersect($product['categories'], $categories) => 'excluded category',
                in_array($product['type'], $types, true) => 'excluded product type',
                $excludeOutOfStock && !$product['in_stock'] => 'out of stock',
                default => null,
            };
            if ($reason !== null) {
                $excluded[$productId] = $reason;
            } else {
                $eligible[$productId] = $product;
            }
        }
        return ['eligible' => $eligible, 'excluded' => $excluded];
    }
}
