<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Store views where the module is enabled
 */
class StoreResolver
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @param bool $includeDisabled ignore the module enable flag
     * @return int[]
     */
    public function getStoreIds(bool $includeDisabled = false): array
    {
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive() && ($includeDisabled || $this->config->isEnabled((int)$store->getId()))) {
                $ids[] = (int)$store->getId();
            }
        }
        return $ids;
    }

    /**
     * @param int $storeId
     * @return int
     */
    public function getWebsiteId(int $storeId): int
    {
        return (int)$this->storeManager->getStore($storeId)->getWebsiteId();
    }
}
