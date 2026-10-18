<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Rule;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule\CollectionFactory;
use Saurabhg\AIproductRecommend\Model\Rule;

/**
 * Applies merchant business rules deterministically:
 * rules run by priority (high first, then rule ID); exclusions always win over boosts and pins;
 * the first rule pinning a slot keeps it; "stop further rules" ends processing.
 */
class RuleProcessor
{
    /**
     * @var Rule[]|null
     */
    private ?array $activeRules = null;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ConditionEvaluator $evaluator
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConditionEvaluator $evaluator,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Applicable rules for the request, in execution order
     *
     * @param Context $context
     * @param string|null $unitCode
     * @return Rule[]
     */
    public function getRules(Context $context, ?string $unitCode): array
    {
        $values = $this->getConditionValues($context);
        $applicable = [];
        foreach ($this->getActiveRules() as $rule) {
            if (!$rule->isForStore($context->storeId)) {
                continue;
            }
            $groups = $rule->getIntCsv('customer_group_ids');
            if ($groups && !in_array($context->customerGroupId, $groups, true)) {
                continue;
            }
            $units = $rule->getCsv('unit_codes');
            if ($units && ($unitCode === null || !in_array($unitCode, $units, true))) {
                continue;
            }
            if (!$this->evaluator->matchAll($rule->getJson('conditions'), $values)) {
                continue;
            }
            $applicable[] = $rule;
            if ((int)$rule->getData('stop_further')) {
                break;
            }
        }
        return $applicable;
    }

    /**
     * Context values available to rule conditions
     *
     * @param Context $context
     * @return array
     */
    public function getConditionValues(Context $context): array
    {
        return [
            'page_type' => $context->pageType,
            'customer_group' => $context->customerGroupId,
            'segment' => $context->segmentIds,
            'logged_in' => $context->customerId ? 1 : 0,
            'context_sku' => $context->productSku,
            'context_category' => array_values(array_unique(array_filter(
                array_merge([$context->categoryId], $context->productCategoryIds)
            ))),
            'cart_sku' => $context->cartSkus,
            'cart_items_count' => count($context->cartProductIds),
        ];
    }

    /**
     * Attribute codes the rules need loaded on candidates
     *
     * @param Rule[] $rules
     * @return string[]
     */
    public function getRequiredAttributes(array $rules): array
    {
        $codes = [];
        foreach ($rules as $rule) {
            foreach ($rule->getJson('actions') as $action) {
                if (($action['type'] ?? '') === 'boost_attribute' && preg_match('/^[a-z][a-z0-9_]{0,59}$/', (string)$action['value'])) {
                    $codes[] = (string)$action['value'];
                }
            }
        }
        return array_values(array_unique($codes));
    }

    /**
     * SKUs pinned by rules (in priority order, first wins)
     *
     * @param Rule[] $rules
     * @return array<string, int> SKU => slot
     */
    public function getPins(array $rules): array
    {
        $pins = [];
        $taken = [];
        foreach ($rules as $rule) {
            foreach ($rule->getJson('actions') as $action) {
                if (($action['type'] ?? '') !== 'pin_sku') {
                    continue;
                }
                $sku = trim((string)($action['value'] ?? ''));
                $slot = max(1, (int)($action['amount'] ?? 1));
                if ($sku !== '' && !isset($pins[$sku]) && !isset($taken[$slot])) {
                    $pins[$sku] = $slot;
                    $taken[$slot] = true;
                }
            }
        }
        return $pins;
    }

    /**
     * @param Rule[] $rules
     * @param array<int, array> $facts
     * @return array{excluded: array<int, string>, boosts: array<int, float>, applied: array<int, string>}
     */
    public function apply(array $rules, array $facts): array
    {
        $excluded = [];
        $boosts = [];
        $applied = [];
        foreach ($rules as $rule) {
            $label = 'rule #' . $rule->getId() . ' ' . $rule->getData('name');
            $hit = false;
            foreach ($rule->getJson('actions') as $action) {
                $type = (string)($action['type'] ?? '');
                $value = (string)($action['value'] ?? '');
                $amount = is_numeric($action['amount'] ?? null) ? (float)$action['amount'] : 0.0;
                $attributeMax = $type === 'boost_attribute' ? $this->maxAttribute($facts, $value) : 0.0;
                foreach ($facts as $productId => $product) {
                    if (str_starts_with($type, 'exclude_')) {
                        if (!isset($excluded[$productId]) && $this->matchesExclusion($type, $value, $product)) {
                            $excluded[$productId] = $label;
                            $hit = true;
                        }
                        continue;
                    }
                    $boost = $this->boostFor($type, $value, $amount, $product, $attributeMax);
                    if ($boost != 0.0) {
                        $boosts[$productId] = ($boosts[$productId] ?? 0.0) + $boost;
                        $hit = true;
                    }
                }
                if ($type === 'pin_sku') {
                    $hit = true;
                }
            }
            if ($hit) {
                $applied[(int)$rule->getId()] = (string)$rule->getData('name');
            }
        }
        return ['excluded' => $excluded, 'boosts' => $boosts, 'applied' => $applied];
    }

    /**
     * @param string $type
     * @param string $value
     * @param array $product
     * @return bool
     */
    private function matchesExclusion(string $type, string $value, array $product): bool
    {
        return match ($type) {
            'exclude_sku' => $this->inList($product['sku'], $value),
            'exclude_category' => (bool)array_intersect($product['categories'], $this->intList($value)),
            'exclude_brand' => $this->brandMatches($product, $value),
            'exclude_type' => $this->inList($product['type'], $value),
            'exclude_out_of_stock' => empty($product['in_stock']),
            'exclude_low_stock' => !empty($product['low_stock']),
            'exclude_price_below' => is_numeric($value) && (float)$product['price'] < (float)$value,
            'exclude_price_above' => is_numeric($value) && (float)$product['price'] > (float)$value,
            default => false,
        };
    }

    /**
     * @param string $type
     * @param string $value
     * @param float $amount
     * @param array $product
     * @param float $attributeMax
     * @return float
     */
    private function boostFor(string $type, string $value, float $amount, array $product, float $attributeMax): float
    {
        switch ($type) {
            case 'boost_sku':
                return $this->inList($product['sku'], $value) ? $amount : 0.0;
            case 'boost_category':
                return array_intersect($product['categories'], $this->intList($value)) ? $amount : 0.0;
            case 'boost_brand':
                return $this->brandMatches($product, $value) ? $amount : 0.0;
            case 'boost_on_sale':
                return !empty($product['on_sale']) ? $amount : 0.0;
            case 'boost_low_stock':
                return !empty($product['low_stock']) ? $amount : 0.0;
            case 'boost_new':
                $days = is_numeric($value) ? (int)$value : 30;
                $created = strtotime((string)$product['created_at']);
                return $created && $created >= time() - $days * 86400 ? $amount : 0.0;
            case 'boost_attribute':
                $raw = $product['attributes'][$value] ?? null;
                return $attributeMax > 0 && is_numeric($raw) ? $amount * ((float)$raw / $attributeMax) : 0.0;
            default:
                return 0.0;
        }
    }

    /**
     * @param array $facts
     * @param string $code
     * @return float
     */
    private function maxAttribute(array $facts, string $code): float
    {
        $max = 0.0;
        foreach ($facts as $product) {
            $raw = $product['attributes'][$code] ?? null;
            if (is_numeric($raw)) {
                $max = max($max, abs((float)$raw));
            }
        }
        return $max;
    }

    /**
     * @param array $product
     * @param string $value
     * @return bool
     */
    private function brandMatches(array $product, string $value): bool
    {
        $candidates = array_filter([(string)($product['brand'] ?? ''), (string)($product['brand_label'] ?? '')], 'strlen');
        foreach (Config::splitList(str_replace(' ', '_', $value)) as $expected) {
            foreach ($candidates as $actual) {
                if (strcasecmp(str_replace(' ', '_', $actual), $expected) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $actual
     * @param string $list
     * @return bool
     */
    private function inList(string $actual, string $list): bool
    {
        foreach (Config::splitList($list) as $item) {
            if (strcasecmp($actual, $item) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $list
     * @return int[]
     */
    private function intList(string $list): array
    {
        return array_map('intval', array_filter(Config::splitList($list), 'is_numeric'));
    }

    /**
     * @return Rule[]
     */
    private function getActiveRules(): array
    {
        if ($this->activeRules === null) {
            $today = $this->timezone->date()->format('Y-m-d');
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('status', 1)
                ->addFieldToFilter('from_date', [['null' => true], ['lteq' => $today]])
                ->addFieldToFilter('to_date', [['null' => true], ['gteq' => $today]])
                ->setOrder('priority', 'DESC')
                ->setOrder('rule_id', 'ASC');
            $this->activeRules = array_values($collection->getItems());
        }
        return $this->activeRules;
    }
}
