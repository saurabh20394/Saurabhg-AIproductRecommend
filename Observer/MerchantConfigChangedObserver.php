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
use Saurabhg\AIproductRecommend\Model\Recommendation\CacheManager;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Result;

/**
 * Units, rules, campaigns, segments, experiments or configuration changed:
 * drop cached and last-good results so exclusions apply immediately
 */
class MerchantConfigChangedObserver implements ObserverInterface
{
    /**
     * @param CacheManager $cacheManager
     * @param Result $result
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly Result $result
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $this->cacheManager->cleanAll();
        $this->result->clear();
    }
}
