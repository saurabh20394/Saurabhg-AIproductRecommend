<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Event;

use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Event as EventResource;

/**
 * Persists validated events idempotently, derives attribution and updates profiles (queue consumer side)
 */
class EventProcessor
{
    private const EVENT_COLUMNS = [
        'event_uuid', 'event_type', 'subject_id', 'sku', 'category_id', 'search_query', 'qty', 'revenue',
        'order_id', 'visitor_id', 'customer_id', 'store_id', 'unit_code', 'position', 'experiment_id',
        'variant_id', 'source', 'created_at',
    ];

    private const INTERACTION_COLUMNS = [
        'event_uuid', 'request_id', 'unit_code', 'product_id', 'position', 'visitor_id', 'customer_id',
        'experiment_id', 'variant_id', 'store_id', 'created_at',
    ];

    /**
     * @param EventResource $eventResource
     * @param Catalog $catalog
     * @param AttributionService $attributionService
     * @param ProfileService $profileService
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly EventResource $eventResource,
        private readonly Catalog $catalog,
        private readonly AttributionService $attributionService,
        private readonly ProfileService $profileService,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @param array $rows normalized rows (EventValidator)
     * @return int number of new rows stored
     */
    public function process(array $rows): int
    {
        $rows = $this->resolveProducts($rows);
        if (!$rows) {
            return 0;
        }
        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['event_type']][] = $row;
        }

        $stored = 0;
        $impressions = $this->eventResource->insertNew(
            EventResource::IMPRESSION_TABLE,
            $this->map($byType['recommendation_impression'] ?? [], self::INTERACTION_COLUMNS)
        );
        $stored += count($impressions);
        $clicks = $this->eventResource->insertNew(
            EventResource::CLICK_TABLE,
            $this->map($byType['recommendation_click'] ?? [], self::INTERACTION_COLUMNS)
        );
        $stored += count($clicks);
        $insertedClickIds = array_flip(array_column($clicks, 'event_uuid'));

        $events = [];
        foreach ($byType as $type => $typeRows) {
            if (!in_array($type, ['recommendation_impression', 'recommendation_click'], true)) {
                $events = array_merge($events, $typeRows);
            }
        }
        $events = array_merge($events, $this->attributionService->attributeAddToCart($byType['add_to_cart'] ?? []));
        $insertedEvents = $this->eventResource->insertNew(EventResource::TABLE, $this->map($events, self::EVENT_COLUMNS));
        $stored += count($insertedEvents);

        if (!empty($byType['purchase'])) {
            $inserted = array_flip(array_column($insertedEvents, 'event_uuid'));
            $purchases = array_filter($byType['purchase'], static fn($row) => isset($inserted[$row['event_uuid']]));
            $conversions = $this->attributionService->attributePurchases($purchases);
            $this->eventResource->insertIgnore(
                EventResource::CONVERSION_TABLE,
                array_map(static function ($row) {
                    unset($row['event_uuid']);
                    return $row;
                }, $conversions)
            );
        }

        // profile updates only from rows stored for the first time (idempotent re-delivery)
        $profileRows = $insertedEvents;
        foreach ($byType['recommendation_click'] ?? [] as $row) {
            if (isset($insertedClickIds[$row['event_uuid']])) {
                $profileRows[] = $row;
            }
        }
        $this->updateProfiles($profileRows);
        return $stored;
    }

    /**
     * @param array $rows
     * @return void
     */
    private function updateProfiles(array $rows): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $storeId = (int)$row['store_id'];
            $customerId = !empty($row['customer_id']) ? (int)$row['customer_id'] : null;
            if ($customerId && !$this->config->isSetFlag('tracking/personalize_customers', $storeId)) {
                $customerId = null;
            }
            $visitorId = $customerId ? null : ($row['visitor_id'] ?? null);
            if (!$customerId && !$visitorId) {
                continue;
            }
            $key = $customerId ? 'c' . $customerId : 'v' . $visitorId;
            $groups[$key]['customer_id'] = $customerId;
            $groups[$key]['visitor_id'] = $visitorId;
            $groups[$key]['store_id'] = $storeId;
            $groups[$key]['rows'][] = $row;
        }
        if (!$groups) {
            return;
        }
        $productIds = [];
        foreach ($rows as $row) {
            if (!empty($row['subject_id'])) {
                $productIds[] = (int)$row['subject_id'];
            } elseif (!empty($row['product_id'])) {
                $productIds[] = (int)$row['product_id'];
            }
        }
        $facts = $this->loadFacts(array_values(array_unique($productIds)), (int)reset($groups)['store_id']);
        foreach ($groups as $group) {
            $events = array_map(static function ($row) {
                $row['subject_id'] = $row['subject_id'] ?? $row['product_id'] ?? null;
                return $row;
            }, $group['rows']);
            $this->profileService->applyEvents($group['customer_id'], $group['visitor_id'], $group['store_id'], $events, $facts);
        }
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array{categories: int[], brand: ?string, price: ?float}>
     */
    private function loadFacts(array $productIds, int $storeId): array
    {
        if (!$productIds) {
            return [];
        }
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $categories = $this->catalog->getCategoryIds($productIds);
        $prices = $this->catalog->getIndexedPrices($productIds, $websiteId);
        $brandAttribute = $this->config->getBrandAttribute($storeId);
        $brands = $brandAttribute !== '' ? $this->catalog->getAttributeValues($productIds, $brandAttribute, $storeId) : [];
        $facts = [];
        foreach ($productIds as $productId) {
            $facts[$productId] = [
                'categories' => $categories[$productId] ?? [],
                'brand' => isset($brands[$productId]) ? (string)$brands[$productId] : null,
                'price' => $prices[$productId] ?? null,
            ];
        }
        return $facts;
    }

    /**
     * Fill product ID / SKU in both directions; drop product events for unknown products
     *
     * @param array $rows
     * @return array
     */
    private function resolveProducts(array $rows): array
    {
        $skus = [];
        $ids = [];
        foreach ($rows as $row) {
            if (empty($row['subject_id']) && !empty($row['sku'])) {
                $skus[] = $row['sku'];
            } elseif (!empty($row['subject_id'])) {
                $ids[] = (int)$row['subject_id'];
            }
        }
        $idsBySku = array_change_key_case($this->catalog->getIdsBySkus($skus), CASE_LOWER);
        $skusById = $this->catalog->getSkus($ids);
        $result = [];
        foreach ($rows as $row) {
            if (empty($row['subject_id']) && !empty($row['sku'])) {
                $row['subject_id'] = $idsBySku[strtolower($row['sku'])] ?? null;
            } elseif (!empty($row['subject_id'])) {
                $row['sku'] = $skusById[(int)$row['subject_id']] ?? null;
                if ($row['sku'] === null) {
                    $row['subject_id'] = null;
                }
            }
            $needsProduct = !in_array($row['event_type'], ['category_view', 'search'], true);
            if ($needsProduct && empty($row['subject_id'])) {
                continue;
            }
            $row['product_id'] = $row['subject_id'];
            $result[] = $row;
        }
        return $result;
    }

    /**
     * @param array $rows
     * @param string[] $columns
     * @return array
     */
    private function map(array $rows, array $columns): array
    {
        $template = array_fill_keys($columns, null);
        return array_map(static fn($row) => array_intersect_key(array_replace($template, $row), $template), $rows);
    }
}
