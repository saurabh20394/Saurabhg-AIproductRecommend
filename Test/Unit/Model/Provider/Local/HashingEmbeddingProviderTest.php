<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Provider\Local;

use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Provider\Local\HashingEmbeddingProvider;
use Saurabhg\AIproductRecommend\Model\Provider\VectorMath;

class HashingEmbeddingProviderTest extends TestCase
{
    private HashingEmbeddingProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new HashingEmbeddingProvider(128);
    }

    public function testDeterministicNormalizedVectorsWithPreservedKeys(): void
    {
        $result = $this->provider->embed(['a' => 'Blue running shoes', 7 => 'Blue running shoes']);
        $this->assertSame(['a', 7], array_keys($result));
        $this->assertCount(128, $result['a']);
        $this->assertSame($result['a'], $result[7]);
        $norm = sqrt(array_sum(array_map(static fn($v) => $v * $v, $result['a'])));
        $this->assertEqualsWithDelta(1.0, $norm, 1e-9);
    }

    public function testRelatedProductsAreMoreSimilarThanUnrelated(): void
    {
        $shoe = $this->provider->embedOne("name^3: Trail Running Shoe\ncategories^2: Footwear, Running\ndescription: lightweight breathable mesh shoe");
        $shoe2 = $this->provider->embedOne("name^3: Road Running Shoes\ncategories^2: Footwear, Running\ndescription: cushioned shoe for runners");
        $mug = $this->provider->embedOne("name^3: Ceramic Coffee Mug\ncategories^2: Kitchen\ndescription: dishwasher safe mug");
        $this->assertGreaterThan(VectorMath::cosine($shoe, $mug) + 0.2, VectorMath::cosine($shoe, $shoe2));
    }

    public function testHtmlAndStopwordsAreIgnored(): void
    {
        $this->assertEqualsWithDelta(
            $this->provider->embedOne('organic cotton shirt'),
            $this->provider->embedOne('<p>The <b>organic</b> cotton shirt</p>'),
            1e-9
        );
    }

    public function testModelMetadata(): void
    {
        $this->assertSame('local-hashing-128', $this->provider->getModel());
        $this->assertSame(HashingEmbeddingProvider::VERSION, $this->provider->getModelVersion());
    }
}
