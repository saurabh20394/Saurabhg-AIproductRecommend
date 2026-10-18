<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Rule\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    #[DataProvider('operators')]
    public function testOperators($actual, string $operator, string $expected, bool $result): void
    {
        $this->assertSame($result, $this->evaluator->match($actual, $operator, $expected));
    }

    public static function operators(): array
    {
        return [
            'eq scalar' => ['product', 'eq', 'Product', true],
            'eq array any' => [[3, 7], 'eq', '7', true],
            'neq array none' => [[3, 7], 'neq', '5', true],
            'neq array hit' => [[3, 7], 'neq', '7', false],
            'in list' => ['cart', 'in', 'product, cart', true],
            'nin list' => [['SKU-1'], 'nin', 'sku-2,sku-3', true],
            'contains' => [['Blue Shirt'], 'contains', 'shirt', true],
            'gt' => [10, 'gt', '5', true],
            'gte equal' => [5, 'gte', '5', true],
            'lt fails' => [10, 'lt', '5', false],
            'lte array' => [[20, 3], 'lte', '3', true],
            'numeric op non numeric' => ['abc', 'gt', '1', false],
            'null actual' => [null, 'eq', '1', false],
            'unknown operator' => [1, 'regex', '1', false],
        ];
    }

    public function testMatchAllAndAny(): void
    {
        $values = ['page_type' => 'product', 'customer_group' => 1];
        $conditions = [
            ['field' => 'page_type', 'operator' => 'eq', 'value' => 'product'],
            ['field' => 'customer_group', 'operator' => 'eq', 'value' => '2'],
        ];
        $this->assertFalse($this->evaluator->matchAll($conditions, $values, 'all'));
        $this->assertTrue($this->evaluator->matchAll($conditions, $values, 'any'));
        $this->assertTrue($this->evaluator->matchAll([], $values), 'no conditions always match');
        $this->assertTrue($this->evaluator->matchAll([['field' => '']], $values), 'empty rows are ignored');
    }
}
