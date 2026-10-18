<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Rule;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule\CollectionFactory;
use Saurabhg\AIproductRecommend\Model\Rule;
use Saurabhg\AIproductRecommend\Model\Rule\ConditionEvaluator;
use Saurabhg\AIproductRecommend\Model\Rule\RuleProcessor;

class RuleProcessorTest extends TestCase
{
    private RuleProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new RuleProcessor(
            $this->createStub(CollectionFactory::class),
            new ConditionEvaluator(),
            $this->createStub(TimezoneInterface::class)
        );
    }

    private function rule(int $id, array $actions): Rule
    {
        $rule = $this->createStub(Rule::class);
        $rule->method('getId')->willReturn($id);
        $rule->method('getData')->willReturnCallback(static fn($key = '') => $key === 'name' ? 'Rule ' . $id : null);
        $rule->method('getJson')->willReturnCallback(static fn($field) => $field === 'actions' ? $actions : []);
        return $rule;
    }

    private function product(int $id, string $sku, array $extra = []): array
    {
        return array_merge([
            'id' => $id, 'sku' => $sku, 'type' => 'simple', 'categories' => [], 'brand' => null, 'brand_label' => null,
            'price' => 50.0, 'on_sale' => false, 'in_stock' => true, 'qty' => 10.0, 'low_stock' => false,
            'created_at' => '2000-01-01 00:00:00', 'excluded_flag' => false, 'attributes' => [],
        ], $extra);
    }

    public function testExclusionsBoostsAndAttributeBoost(): void
    {
        $facts = [
            1 => $this->product(1, 'SKU-A', ['categories' => [4], 'attributes' => ['margin' => 40]]),
            2 => $this->product(2, 'SKU-B', ['brand' => '12', 'brand_label' => 'Acme', 'attributes' => ['margin' => 10]]),
            3 => $this->product(3, 'SKU-C', ['in_stock' => false, 'price' => 5.0]),
            4 => $this->product(4, 'SKU-D', ['on_sale' => true, 'created_at' => gmdate('Y-m-d H:i:s')]),
        ];
        $rules = [
            $this->rule(1, [
                ['type' => 'exclude_out_of_stock', 'value' => '', 'amount' => ''],
                ['type' => 'boost_category', 'value' => '4,9', 'amount' => '0.3'],
                ['type' => 'boost_brand', 'value' => 'acme', 'amount' => '0.2'],
                ['type' => 'boost_attribute', 'value' => 'margin', 'amount' => '0.4'],
            ]),
            $this->rule(2, [
                ['type' => 'boost_on_sale', 'value' => '', 'amount' => '0.1'],
                ['type' => 'boost_new', 'value' => '7', 'amount' => '0.05'],
                ['type' => 'exclude_price_below', 'value' => '1', 'amount' => ''],
                ['type' => 'exclude_sku', 'value' => 'sku-b', 'amount' => ''],
            ]),
        ];
        $outcome = $this->processor->apply($rules, $facts);

        $this->assertSame([3 => 'rule #1 Rule 1', 2 => 'rule #2 Rule 2'], $outcome['excluded']);
        $this->assertEqualsWithDelta(0.3 + 0.4, $outcome['boosts'][1], 1e-9);
        $this->assertEqualsWithDelta(0.15, $outcome['boosts'][4], 1e-9);
        $this->assertSame([1 => 'Rule 1', 2 => 'Rule 2'], $outcome['applied']);
    }

    public function testPinsFirstRuleWinsSlotAndSku(): void
    {
        $rules = [
            $this->rule(1, [['type' => 'pin_sku', 'value' => 'SKU-A', 'amount' => '1']]),
            $this->rule(2, [
                ['type' => 'pin_sku', 'value' => 'SKU-B', 'amount' => '1'],
                ['type' => 'pin_sku', 'value' => 'SKU-A', 'amount' => '3'],
                ['type' => 'pin_sku', 'value' => 'SKU-C', 'amount' => '2'],
            ]),
        ];
        $this->assertSame(['SKU-A' => 1, 'SKU-C' => 2], $this->processor->getPins($rules));
    }

    public function testRequiredAttributesAreValidatedCodes(): void
    {
        $rules = [$this->rule(1, [
            ['type' => 'boost_attribute', 'value' => 'margin'],
            ['type' => 'boost_attribute', 'value' => 'bad code;drop'],
        ])];
        $this->assertSame(['margin'], $this->processor->getRequiredAttributes($rules));
    }
}
