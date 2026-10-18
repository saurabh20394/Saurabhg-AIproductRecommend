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
use Saurabhg\AIproductRecommend\Model\Recommendation\Ranker;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

class RankerTest extends TestCase
{
    private Ranker $ranker;

    protected function setUp(): void
    {
        $this->ranker = new Ranker();
    }

    private function facts(int $id, array $categories = [], bool $inStock = true, bool $low = false, ?string $brand = null, float $price = 10.0): array
    {
        return ['id' => $id, 'categories' => $categories, 'in_stock' => $inStock, 'low_stock' => $low, 'brand' => $brand, 'price' => $price];
    }

    public function testWeightsProfilesOverridesAndRemote(): void
    {
        $config = ['behavior' => 0.3, 'similarity' => 0.25, 'affinity' => 0.15, 'popularity' => 0.1, 'trending' => 0.1, 'conversion' => 0.05, 'inventory' => 0.05];
        $this->assertSame(['recency' => 1.0], $this->ranker->getWeights(UnitType::RECENTLY_VIEWED, $config));
        $hybrid = $this->ranker->getWeights(UnitType::HYBRID, $config, ['similarity' => 0.9, 'unknown' => 5, 'trending' => 'x']);
        $this->assertSame(0.9, $hybrid['similarity']);
        $this->assertArrayNotHasKey('unknown', $hybrid);
        $this->assertSame(0.1, $hybrid['trending']);
        $this->assertArrayNotHasKey('recency', $hybrid, 'zero weights are dropped');
        $this->assertSame(['ai' => 1.0], $this->ranker->getWeights(UnitType::SIMILAR, $config, [], true));
    }

    public function testAffinityCombinesCategoryBrandAndPrice(): void
    {
        $profile = ['cat' => ['5' => 4.0, '6' => 2.0], 'brand' => ['77' => 3.0], 'price_sum' => 100.0, 'price_n' => 10];
        $this->assertEqualsWithDelta(1.0, $this->ranker->affinity($this->facts(1, [5], true, false, '77', 10.0), $profile), 1e-6);
        $this->assertEqualsWithDelta(0.3 + 0.15 * 0.5, $this->ranker->affinity($this->facts(2, [6], true, false, null, 15.0), $profile), 1e-6);
        $this->assertSame(0.0, $this->ranker->affinity($this->facts(3, [5]), []));
    }

    public function testInventoryFeature(): void
    {
        $this->assertSame(1.0, $this->ranker->inventory($this->facts(1)));
        $this->assertSame(0.5, $this->ranker->inventory($this->facts(1, [], true, true)));
        $this->assertSame(0.0, $this->ranker->inventory($this->facts(1, [], false)));
    }

    public function testRankOrdersByFinalScoreWithDeterministicTieBreakAndBoostReason(): void
    {
        $set = (new CandidateSet())
            ->add(3, 'similarity', 0.5, 'similar')
            ->add(1, 'similarity', 0.5, 'similar')
            ->add(2, 'similarity', 0.9, 'similar')
            ->add(9, 'similarity', 1.0, 'similar');
        $facts = [1 => $this->facts(1), 2 => $this->facts(2), 3 => $this->facts(3)];
        $ranked = $this->ranker->rank($set, $facts, [], [], ['similarity' => 1.0], [3 => 0.6]);

        $this->assertSame([3, 2, 1], array_keys($ranked), 'ineligible product 9 is dropped; boost lifts 3');
        $this->assertSame('featured', $ranked[3]['reason']);
        $this->assertSame('similar', $ranked[2]['reason']);
        $this->assertEqualsWithDelta(1.1, $ranked[3]['final'], 1e-6);

        $tie = $this->ranker->rank($set, $facts, [], [], ['similarity' => 1.0], []);
        $this->assertSame([2, 1, 3], array_keys($tie), 'equal scores are ordered by product ID');
    }

    public function testStatsFeedPopularityAndTrending(): void
    {
        $set = (new CandidateSet())->touch(1)->touch(2);
        $facts = [1 => $this->facts(1), 2 => $this->facts(2)];
        $stats = [1 => ['popularity_score' => 0.2, 'trending_score' => 0.9, 'conversion_score' => 0.0],
            2 => ['popularity_score' => 0.8, 'trending_score' => 0.1, 'conversion_score' => 0.0]];
        $ranked = $this->ranker->rank($set, $facts, $stats, [], ['trending' => 1.0], []);
        $this->assertSame([1, 2], array_keys($ranked));
        $this->assertSame('trending', $ranked[1]['reason']);
    }
}
