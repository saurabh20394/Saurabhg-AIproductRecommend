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
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Privacy\DataEraser;

/**
 * customer_delete_before: cascade erasure to derived recommendation data (runs inside the delete transaction)
 */
class CustomerDeleteObserver implements ObserverInterface
{
    /**
     * @param DataEraser $dataEraser
     * @param Logger $logger
     */
    public function __construct(
        private readonly DataEraser $dataEraser,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getData('customer');
        if ($customer && $customer->getId()) {
            try {
                $this->dataEraser->forgetCustomer((int)$customer->getId());
            } catch (\Throwable $e) {
                $this->logger->error('Recommendation data erasure failed', ['error' => $e]);
            }
        }
    }
}
