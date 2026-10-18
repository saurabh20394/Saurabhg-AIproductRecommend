<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Personalization;

use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Segment\CollectionFactory;
use Saurabhg\AIproductRecommend\Model\Rule\ConditionEvaluator;
use Saurabhg\AIproductRecommend\Model\Segment;

/**
 * Evaluates segment membership from the derived profile at request time
 */
class SegmentResolver
{
    /**
     * @var Segment[]|null
     */
    private ?array $segments = null;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ConditionEvaluator $evaluator
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConditionEvaluator $evaluator
    ) {
    }

    /**
     * @param Context $context
     * @return int[] matching segment IDs
     */
    public function resolve(Context $context): array
    {
        $values = $this->getValues($context);
        $matched = [];
        foreach ($this->getSegments() as $segment) {
            $conditions = $segment->getJson('conditions');
            if (!$conditions) {
                continue;
            }
            if ($this->evaluator->matchAll($conditions, $values, (string)$segment->getData('match_type'))) {
                $matched[] = (int)$segment->getId();
            }
        }
        return $matched;
    }

    /**
     * Field values available to segment conditions
     *
     * @param Context $context
     * @return array
     */
    public function getValues(Context $context): array
    {
        $profile = $context->profile;
        $orders = (int)($profile['orders'] ?? 0);
        $revenue = (float)($profile['revenue'] ?? 0);
        $lastOrder = (int)($profile['last_order_at'] ?? 0);
        $priceCount = (int)($profile['price_n'] ?? 0);
        return [
            'customer_group' => $context->customerGroupId,
            'logged_in' => $context->customerId ? 1 : 0,
            'profile_type' => $context->profileType,
            'orders_count' => $orders,
            'lifetime_revenue' => $revenue,
            'avg_order_value' => $orders ? round($revenue / $orders, 2) : 0,
            'days_since_last_order' => $lastOrder ? (int)floor((time() - $lastOrder) / 86400) : null,
            'event_count' => (int)($profile['events'] ?? 0),
            'top_category' => ProfileService::top($profile, 'cat', 3),
            'top_brand' => ProfileService::top($profile, 'brand', 3),
            'avg_viewed_price' => $priceCount ? round((float)$profile['price_sum'] / $priceCount, 2) : null,
        ];
    }

    /**
     * @return Segment[]
     */
    private function getSegments(): array
    {
        if ($this->segments === null) {
            $this->segments = $this->collectionFactory->create()->addFieldToFilter('status', 1)->getItems();
        }
        return $this->segments;
    }
}
