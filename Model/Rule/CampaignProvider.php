<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Rule;

use Saurabhg\AIproductRecommend\Model\Campaign;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Campaign\CollectionFactory;

/**
 * Time-bound campaign boosts
 */
class CampaignProvider
{
    /**
     * @var Campaign[]|null
     */
    private ?array $active = null;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param Context $context
     * @param string|null $unitCode
     * @param array<int, array> $facts
     * @return array{boosts: array<int, float>, applied: array<int, string>}
     */
    public function getBoosts(Context $context, ?string $unitCode, array $facts): array
    {
        $boosts = [];
        $applied = [];
        foreach ($this->getActive() as $campaign) {
            if (!$campaign->isForStore($context->storeId)) {
                continue;
            }
            $units = $campaign->getCsv('unit_codes');
            if ($units && ($unitCode === null || !in_array($unitCode, $units, true))) {
                continue;
            }
            $segments = $campaign->getIntCsv('segment_ids');
            if ($segments && !array_intersect($segments, $context->segmentIds)) {
                continue;
            }
            $skus = array_map('strtolower', $campaign->getCsv('boost_skus'));
            $categories = $campaign->getIntCsv('boost_category_ids');
            $amount = (float)$campaign->getData('boost_amount');
            foreach ($facts as $productId => $product) {
                if (in_array(strtolower($product['sku']), $skus, true) || array_intersect($product['categories'], $categories)) {
                    $boosts[$productId] = ($boosts[$productId] ?? 0.0) + $amount;
                    $applied[(int)$campaign->getId()] = (string)$campaign->getData('name');
                }
            }
        }
        return ['boosts' => $boosts, 'applied' => $applied];
    }

    /**
     * @return Campaign[]
     */
    private function getActive(): array
    {
        if ($this->active === null) {
            $now = gmdate('Y-m-d H:i:s');
            $this->active = array_values($this->collectionFactory->create()
                ->addFieldToFilter('status', 1)
                ->addFieldToFilter('start_at', [['null' => true], ['lteq' => $now]])
                ->addFieldToFilter('end_at', [['null' => true], ['gteq' => $now]])
                ->getItems());
        }
        return $this->active;
    }
}
