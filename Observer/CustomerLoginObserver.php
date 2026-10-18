<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * customer_login: merge the anonymous visitor profile into the customer profile (idempotent)
 */
class CustomerLoginObserver implements ObserverInterface
{
    /**
     * @param ProfileService $profileService
     * @param VisitorIdentity $visitorIdentity
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly VisitorIdentity $visitorIdentity,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getData('customer');
        if (!$customer || !$customer->getId()) {
            return;
        }
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            if (!$this->config->isSetFlag('tracking/personalize_customers', $storeId)) {
                return;
            }
            $visitorId = $this->visitorIdentity->getVisitorId($storeId);
            if ($visitorId) {
                $this->profileService->merge($visitorId, (int)$customer->getId());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Profile merge failed', ['error' => $e]);
        }
    }
}
