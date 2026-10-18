<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Personalization;

use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Profile;
use Saurabhg\AIproductRecommend\Model\ProfileFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Event as EventResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Profile as ProfileResource;

/**
 * Derived preference profiles: decayed category / brand affinity, price band, recent interactions,
 * purchase frequency and recency. Stores aggregate features only, never raw PII.
 */
class ProfileService
{
    private const LIMITS = ['cat' => 30, 'brand' => 20, 'recent' => 30, 'cart' => 20, 'purchased' => 50, 'searches' => 10];

    /**
     * Affinity weight per event type
     */
    private const WEIGHTS = [
        'product_view' => 1.0,
        'recommendation_click' => 1.5,
        'wishlist_add' => 2.0,
        'add_to_cart' => 3.0,
        'recommendation_add_to_cart' => 3.0,
        'purchase' => 5.0,
        'category_view' => 0.5,
    ];

    /**
     * @var array<string, array>
     */
    private array $memo = [];

    /**
     * @param ProfileFactory $profileFactory
     * @param ProfileResource $profileResource
     * @param EventResource $eventResource
     * @param Config $config
     */
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileResource $profileResource,
        private readonly EventResource $eventResource,
        private readonly Config $config
    ) {
    }

    /**
     * @param int|null $customerId
     * @param string|null $visitorId
     * @return Profile|null
     */
    public function load(?int $customerId, ?string $visitorId): ?Profile
    {
        $profile = $this->profileFactory->create();
        if ($customerId) {
            $this->profileResource->load($profile, $customerId, 'customer_id');
        } elseif ($visitorId) {
            $this->profileResource->load($profile, $visitorId, 'visitor_id');
        }
        return $profile->getId() ? $profile : null;
    }

    /**
     * Features for ranking (request-memoized); includes profile_type
     *
     * @param int|null $customerId
     * @param string|null $visitorId
     * @return array
     */
    public function getFeatures(?int $customerId, ?string $visitorId): array
    {
        $key = $customerId ? 'c' . $customerId : 'v' . $visitorId;
        if (!$customerId && !$visitorId) {
            return ['profile_type' => Profile::TYPE_ANONYMOUS];
        }
        if (!isset($this->memo[$key])) {
            $profile = $this->load($customerId, $visitorId);
            $features = $profile ? $this->decay($profile->getFeatures()) : [];
            $features['profile_type'] = $profile ? (string)$profile->getData('profile_type')
                : ($customerId ? Profile::TYPE_CUSTOMER : Profile::TYPE_ANONYMOUS);
            $this->memo[$key] = $features;
        }
        return $this->memo[$key];
    }

    /**
     * Apply processed events of one shopper; product facts provide categories, brand and price
     *
     * @param int|null $customerId
     * @param string|null $visitorId
     * @param int $storeId
     * @param array $events normalized event rows
     * @param array<int, array{categories: int[], brand: ?string, price: ?float}> $facts
     * @return void
     */
    public function applyEvents(?int $customerId, ?string $visitorId, int $storeId, array $events, array $facts): void
    {
        if (!$customerId && !$visitorId) {
            return;
        }
        $profile = $this->load($customerId, $visitorId) ?? $this->profileFactory->create();
        if (!$profile->getId()) {
            $profile->setData($customerId ? 'customer_id' : 'visitor_id', $customerId ?: $visitorId);
        }
        $features = $this->decay($profile->getFeatures());
        $orders = [];
        foreach ($events as $event) {
            $type = (string)$event['event_type'];
            $productId = (int)($event['subject_id'] ?? 0);
            $weight = self::WEIGHTS[$type] ?? 0.0;
            $productFacts = $facts[$productId] ?? null;

            if ($type === 'category_view' && !empty($event['category_id'])) {
                $this->bump($features['cat'], (string)(int)$event['category_id'], $weight);
            }
            if ($productFacts && $weight > 0) {
                foreach ($productFacts['categories'] as $categoryId) {
                    $this->bump($features['cat'], (string)$categoryId, $weight);
                }
                if (!empty($productFacts['brand'])) {
                    $this->bump($features['brand'], (string)$productFacts['brand'], $weight);
                }
                if (!empty($productFacts['price']) && in_array($type, ['product_view', 'add_to_cart', 'purchase'], true)) {
                    $features['price_sum'] = (float)($features['price_sum'] ?? 0) + (float)$productFacts['price'];
                    $features['price_n'] = (int)($features['price_n'] ?? 0) + 1;
                }
            }
            switch ($type) {
                case 'product_view':
                case 'recommendation_click':
                    $this->pushFront($features['recent'], $productId);
                    break;
                case 'add_to_cart':
                case 'recommendation_add_to_cart':
                    $this->pushFront($features['cart'], $productId);
                    break;
                case 'remove_from_cart':
                    $features['cart'] = array_values(array_diff($features['cart'] ?? [], [$productId]));
                    break;
                case 'purchase':
                    $this->pushFront($features['purchased'], $productId);
                    $features['cart'] = array_values(array_diff($features['cart'] ?? [], [$productId]));
                    if (!empty($event['order_id'])) {
                        $orders[(int)$event['order_id']] = ($orders[(int)$event['order_id']] ?? 0.0)
                            + (float)($event['revenue'] ?? 0);
                    }
                    break;
                case 'search':
                    if (!empty($event['search_query'])) {
                        $features['searches'] = array_values(array_unique(array_merge(
                            [mb_substr((string)$event['search_query'], 0, 64)],
                            $features['searches'] ?? []
                        )));
                    }
                    break;
            }
        }
        if ($orders) {
            $known = $features['order_ids'] ?? [];
            foreach ($orders as $orderId => $revenue) {
                if (in_array($orderId, $known, true)) {
                    continue;
                }
                $known[] = $orderId;
                $features['orders'] = (int)($features['orders'] ?? 0) + 1;
                $features['revenue'] = round((float)($features['revenue'] ?? 0) + $revenue, 4);
                $features['last_order_at'] = time();
            }
            $features['order_ids'] = array_slice($known, -50);
        }
        $features['events'] = (int)($features['events'] ?? 0) + count($events);
        $features['last_seen'] = time();

        $profile->setFeatures($this->prune($features));
        $profile->setData('event_count', (int)$features['events']);
        $profile->setData('store_id', $storeId);
        $profile->setData('profile_type', $customerId ? Profile::TYPE_CUSTOMER
            : (!empty($features['orders']) ? Profile::TYPE_GUEST : Profile::TYPE_ANONYMOUS));
        $this->profileResource->save($profile);
        unset($this->memo[$customerId ? 'c' . $customerId : 'v' . $visitorId]);
    }

    /**
     * Merge the anonymous visitor profile into the customer profile after login.
     * Idempotent: merged visitor features are cleared, so a repeated merge only adds new activity.
     *
     * @param string $visitorId
     * @param int $customerId
     * @return void
     */
    public function merge(string $visitorId, int $customerId): void
    {
        $this->eventResource->assignVisitorToCustomer($visitorId, $customerId);
        $visitor = $this->load(null, $visitorId);
        if (!$visitor || !$visitor->getFeatures()) {
            return;
        }
        $customer = $this->load($customerId, null) ?? $this->profileFactory->create()->setData('customer_id', $customerId);
        $a = $this->decay($customer->getFeatures());
        $b = $this->decay($visitor->getFeatures());

        foreach (['cat', 'brand'] as $map) {
            foreach ($b[$map] ?? [] as $key => $score) {
                $this->bump($a[$map], (string)$key, (float)$score);
            }
        }
        foreach (['recent', 'cart', 'purchased', 'searches'] as $list) {
            $a[$list] = array_values(array_unique(array_merge($b[$list] ?? [], $a[$list] ?? [])));
        }
        foreach (['price_sum', 'price_n', 'events', 'orders', 'revenue'] as $counter) {
            $a[$counter] = ($a[$counter] ?? 0) + ($b[$counter] ?? 0);
        }
        $a['order_ids'] = array_values(array_unique(array_merge($a['order_ids'] ?? [], $b['order_ids'] ?? [])));
        $a['last_order_at'] = max((int)($a['last_order_at'] ?? 0), (int)($b['last_order_at'] ?? 0)) ?: null;
        $a['last_seen'] = time();

        $customer->setFeatures($this->prune($a));
        $customer->setData('event_count', (int)($a['events'] ?? 0));
        $customer->setData('profile_type', Profile::TYPE_CUSTOMER);
        $this->profileResource->save($customer);

        $visitor->setFeatures([]);
        $visitor->setData('event_count', 0);
        $visitor->setData('merged_into', $customer->getId());
        $this->profileResource->save($visitor);
        $this->memo = [];
    }

    /**
     * Apply exponential time decay to affinity maps
     *
     * @param array $features
     * @return array
     */
    public function decay(array $features): array
    {
        $now = time();
        $decayedAt = (int)($features['decayed_at'] ?? $now);
        $halfLife = max(1, $this->config->getInt('algorithms/profile_half_life_days', null, 14)) * 86400;
        $factor = 0.5 ** (max(0, $now - $decayedAt) / $halfLife);
        if ($factor < 0.999) {
            foreach (['cat', 'brand'] as $map) {
                foreach ($features[$map] ?? [] as $key => $score) {
                    $value = round((float)$score * $factor, 4);
                    if ($value < 0.01) {
                        unset($features[$map][$key]);
                    } else {
                        $features[$map][$key] = $value;
                    }
                }
            }
        }
        $features['decayed_at'] = $now;
        foreach (['cat', 'brand'] as $map) {
            $features[$map] = $features[$map] ?? [];
        }
        foreach (['recent', 'cart', 'purchased', 'searches'] as $list) {
            $features[$list] = $features[$list] ?? [];
        }
        return $features;
    }

    /**
     * Top affinity keys of a map
     *
     * @param array $features
     * @param string $map cat / brand
     * @param int $limit
     * @return string[]
     */
    public static function top(array $features, string $map, int $limit): array
    {
        $values = $features[$map] ?? [];
        arsort($values);
        return array_map('strval', array_slice(array_keys($values), 0, $limit));
    }

    /**
     * @param array|null $map
     * @param string $key
     * @param float $weight
     * @return void
     */
    private function bump(?array &$map, string $key, float $weight): void
    {
        $map ??= [];
        $map[$key] = round((float)($map[$key] ?? 0) + $weight, 4);
    }

    /**
     * @param array|null $list
     * @param int $productId
     * @return void
     */
    private function pushFront(?array &$list, int $productId): void
    {
        if ($productId <= 0) {
            return;
        }
        $list = array_values(array_diff($list ?? [], [$productId]));
        array_unshift($list, $productId);
    }

    /**
     * @param array $features
     * @return array
     */
    private function prune(array $features): array
    {
        foreach (self::LIMITS as $key => $limit) {
            if (!isset($features[$key])) {
                continue;
            }
            if (in_array($key, ['cat', 'brand'], true)) {
                arsort($features[$key]);
                $features[$key] = array_slice($features[$key], 0, $limit, true);
            } else {
                $features[$key] = array_slice(array_values($features[$key]), 0, $limit);
            }
        }
        return $features;
    }
}
