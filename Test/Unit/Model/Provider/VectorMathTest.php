<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Provider;

use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Provider\VectorMath;

class VectorMathTest extends TestCase
{
    public function testPackUnpackRoundTrip(): void
    {
        $vector = [0.5, -0.25, 1.0, 0.0];
        $this->assertEqualsWithDelta($vector, VectorMath::unpack(VectorMath::pack($vector)), 1e-6);
    }

    public function testUnpackRejectsInvalidInput(): void
    {
        $this->assertSame([], VectorMath::unpack(null));
        $this->assertSame([], VectorMath::unpack('not base64 !!'));
        $this->assertSame([], VectorMath::unpack(base64_encode('abc')));
    }

    public function testCosine(): void
    {
        $this->assertEqualsWithDelta(1.0, VectorMath::cosine([1, 2, 3], [2, 4, 6]), 1e-9);
        $this->assertEqualsWithDelta(0.0, VectorMath::cosine([1, 0], [0, 1]), 1e-9);
        $this->assertSame(0.0, VectorMath::cosine([1, 0], [-1, 0]), 'negative similarity is clamped to 0');
        $this->assertSame(0.0, VectorMath::cosine([], [1]));
        $this->assertSame(0.0, VectorMath::cosine([0, 0], [1, 1]));
    }

    public function testNormalize(): void
    {
        $normalized = VectorMath::normalize([3.0, 4.0]);
        $this->assertEqualsWithDelta([0.6, 0.8], $normalized, 1e-9);
        $this->assertSame([0.0, 0.0], VectorMath::normalize([0.0, 0.0]));
    }
}
