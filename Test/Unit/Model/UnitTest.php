<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Unit;

class UnitTest extends TestCase
{
    private function unit(array $data): Unit
    {
        /** @var Unit $unit */
        $unit = (new ObjectManager($this))->getObject(Unit::class);
        return $unit->setData($data);
    }

    public function testPinnedSkusWithExplicitAndImplicitSlots(): void
    {
        $unit = $this->unit(['pinned_skus' => "SKU-A:2\n SKU-B \nSKU-C:5, SKU-D"]);
        $this->assertSame(['SKU-A' => 2, 'SKU-B' => 3, 'SKU-C' => 5, 'SKU-D' => 6], $unit->getPinnedSkus());
    }

    public function testComplementaryMap(): void
    {
        $unit = $this->unit(['complementary_map' => "20:23,24\n*:30\ninvalid line\n20:25"]);
        $this->assertSame(['20' => [23, 24, 25], '*' => [30]], $unit->getComplementaryMap());
    }

    public function testApplicabilityByStoreGroupAndSegment(): void
    {
        $unit = $this->unit(['status' => 1, 'store_ids' => '1,2', 'customer_group_ids' => '1', 'segment_ids' => '7']);
        $this->assertTrue($unit->isApplicable(2, 1, [3, 7]));
        $this->assertFalse($unit->isApplicable(3, 1, [7]), 'wrong store');
        $this->assertFalse($unit->isApplicable(1, 0, [7]), 'wrong group');
        $this->assertFalse($unit->isApplicable(1, 1, []), 'not in segment');
        $this->assertTrue($this->unit(['status' => 1, 'store_ids' => '0'])->isApplicable(9, 4, []));
        $this->assertFalse($this->unit(['status' => 0, 'store_ids' => '0'])->isApplicable(1, 0, []));
    }

    public function testProductCountIsClamped(): void
    {
        $this->assertSame(50, $this->unit(['product_count' => 500])->getProductCount());
        $this->assertSame(6, $this->unit([])->getProductCount());
    }
}
