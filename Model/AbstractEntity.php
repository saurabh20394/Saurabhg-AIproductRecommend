<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\Model\AbstractModel;
use Saurabhg\AIproductRecommend\Model\Cache\Type as CacheType;

/**
 * Merchant-managed entity; saving or deleting one invalidates cached recommendation results
 */
abstract class AbstractEntity extends AbstractModel
{
    /**
     * @var string|bool
     */
    protected $_cacheTag = CacheType::TAG_RESULTS;

    /**
     * Values of a comma-separated column
     *
     * @param string $field
     * @return string[]
     */
    public function getCsv(string $field): array
    {
        return Config::splitList((string)$this->getData($field));
    }

    /**
     * Integer values of a comma-separated column
     *
     * @param string $field
     * @return int[]
     */
    public function getIntCsv(string $field): array
    {
        return array_values(array_unique(array_map('intval', array_filter($this->getCsv($field), 'is_numeric'))));
    }

    /**
     * Decoded JSON column
     *
     * @param string $field
     * @return array
     */
    public function getJson(string $field): array
    {
        $value = $this->getData($field);
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Whether the entity applies to a store view (0 = all store views)
     *
     * @param int $storeId
     * @return bool
     */
    public function isForStore(int $storeId): bool
    {
        $stores = $this->getIntCsv('store_ids');
        return !$stores || in_array(0, $stores, true) || in_array($storeId, $stores, true);
    }
}
