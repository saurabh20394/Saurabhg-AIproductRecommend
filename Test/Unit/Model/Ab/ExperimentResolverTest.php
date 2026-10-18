<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Ab;

use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Ab\ExperimentResolver;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as ExperimentResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment\CollectionFactory;

class ExperimentResolverTest extends TestCase
{
    private ExperimentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ExperimentResolver(
            $this->createStub(CollectionFactory::class),
            $this->createStub(ExperimentResource::class)
        );
    }

    public function testPickIsDeterministicAndFollowsAllocation(): void
    {
        $variants = [10 => ['allocation' => 80], 11 => ['allocation' => 20]];
        $counts = [10 => 0, 11 => 0];
        for ($i = 0; $i < 5000; $i++) {
            $counts[$this->resolver->pick(3, 'v:visitor' . $i, $variants)]++;
        }
        $this->assertSame($this->resolver->pick(3, 'c:42', $variants), $this->resolver->pick(3, 'c:42', $variants));
        $this->assertEqualsWithDelta(0.8, $counts[10] / 5000, 0.03);
    }

    public function testZeroAllocationFallsBackToFirstVariant(): void
    {
        $this->assertSame(5, $this->resolver->pick(1, 'v:x', [5 => ['allocation' => 0], 6 => ['allocation' => 0]]));
        $this->assertSame(6, $this->resolver->pick(1, 'v:x', [5 => ['allocation' => 0], 6 => ['allocation' => 100]]));
    }
}
