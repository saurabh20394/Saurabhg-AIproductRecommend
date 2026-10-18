<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Recommendation;

use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSet;

class CandidateSetTest extends TestCase
{
    public function testKeepsMaximumPerComponentWithReason(): void
    {
        $set = new CandidateSet();
        $set->add(1, 'behavior', 0.3, 'also_viewed')->add(1, 'behavior', 0.8, 'frequently_bought_together')
            ->add(1, 'behavior', 0.5, 'also_viewed');
        $this->assertSame(['behavior' => 0.8], $set->getScores(1));
        $this->assertSame(['behavior' => 'frequently_bought_together'], $set->getReasons(1));
    }

    public function testAccumulateAndIgnoreInvalid(): void
    {
        $set = new CandidateSet();
        $set->add(2, 'behavior', 0.4, 'x', true)->add(2, 'behavior', 0.4, 'x', true)
            ->add(0, 'behavior', 1, 'x')->add(3, 'behavior', 0, 'x');
        $this->assertEqualsWithDelta(0.8, $set->getScores(2)['behavior'], 1e-9);
        $this->assertSame([2], $set->getIds());
    }

    public function testNormalizeRemoveRetainTop(): void
    {
        $set = new CandidateSet();
        $set->addMany([10 => 4.0, 11 => 2.0, 12 => 1.0], 'behavior', 'r');
        $set->touch(13);
        $set->normalize('behavior');
        $this->assertSame(1.0, $set->getScores(10)['behavior']);
        $this->assertSame(0.5, $set->getScores(11)['behavior']);
        $this->assertSame([10, 11], $set->top('behavior', 2));
        $set->remove([10])->retain([11, 13]);
        $this->assertSame([11, 13], $set->getIds());
        $this->assertSame([], $set->getScores(13));
    }

    public function testMerge(): void
    {
        $a = (new CandidateSet())->add(1, 'similarity', 0.2, 'similar');
        $b = (new CandidateSet())->add(1, 'similarity', 0.9, 'similar')->add(2, 'trending', 0.4, 'trending');
        $a->merge($b);
        $this->assertSame(0.9, $a->getScores(1)['similarity']);
        $this->assertSame(['trending' => 'trending'], $a->getReasons(2));
    }
}
