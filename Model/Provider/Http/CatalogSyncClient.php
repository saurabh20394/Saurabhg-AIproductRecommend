<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider\Http;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Pushes product payloads to the external recommendation service: POST {base}{path} {store_id, products[]}
 */
class CatalogSyncClient
{
    /**
     * @param Client $client
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Client $client,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array $payloads product payloads (ProductPayloadBuilder), no customer data
     * @param int $storeId
     * @return void
     * @throws LocalizedException
     */
    public function push(array $payloads, int $storeId): void
    {
        if (!$payloads) {
            return;
        }
        $this->client->postJson(
            $this->config->getServiceUrl('ai/catalog_sync_path', $storeId),
            [
                'store_id' => $this->storeManager->getStore($storeId)->getCode(),
                'products' => array_values($payloads),
            ],
            $storeId,
            max(1, $this->config->getInt('ai/batch_timeout', $storeId, 30)) * 1000
        );
    }
}
