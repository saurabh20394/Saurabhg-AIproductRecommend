<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Provider\ProviderPool;

/**
 * Validates the saved AI provider configuration of a scope (sample product text only, no customer data)
 */
class TestConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::config';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ProviderPool $providerPool
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ProviderPool $providerPool,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $storeId = $this->resolveStoreId();
        $messages = [];
        $success = true;
        try {
            $provider = $this->providerPool->getEmbeddingProvider($storeId);
            $started = microtime(true);
            $vectors = $provider->embed(['test' => 'Organic cotton t-shirt, crew neck, blue'], $storeId);
            $messages[] = (string)__(
                'Embeddings OK: %1 (%2 dimensions, %3 ms).',
                $provider->getModel($storeId),
                count($vectors['test'] ?? []),
                (int)round((microtime(true) - $started) * 1000)
            );
        } catch (\Throwable $e) {
            $success = false;
            $messages[] = (string)__('Embeddings failed: %1', Logger::sanitize($e->getMessage()));
        }
        if ($this->config->isRemoteRankingEnabled($storeId)) {
            try {
                $response = $this->providerPool->getRankingProvider()->recommend([
                    'store_id' => $this->storeManager->getStore($storeId)->getCode(),
                    'visitor_id' => null,
                    'customer_id' => null,
                    'context' => ['page_type' => 'home'],
                    'unit' => ['code' => 'connection_test', 'type' => 'popular', 'limit' => 1],
                ], $storeId);
                $messages[] = (string)__('Recommendation API OK (%1 items, model %2).', count($response['recommendations']), $response['model_version'] ?: '-');
            } catch (\Throwable $e) {
                $success = false;
                $messages[] = (string)__('Recommendation API failed: %1', Logger::sanitize($e->getMessage()));
            }
        }
        return $this->jsonFactory->create()->setData(['success' => $success, 'message' => implode(' ', $messages)]);
    }

    /**
     * @return int
     */
    private function resolveStoreId(): int
    {
        $store = (int)$this->getRequest()->getParam('store');
        if ($store) {
            return $store;
        }
        $website = (int)$this->getRequest()->getParam('website');
        if ($website) {
            $default = $this->storeManager->getWebsite($website)->getDefaultStore();
            return $default ? (int)$default->getId() : 0;
        }
        return (int)$this->storeManager->getDefaultStoreView()->getId();
    }
}
