<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Provider\Http;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Provider\Http\Client;
use Saurabhg\AIproductRecommend\Model\Provider\Http\HttpRankingProvider;

class HttpRankingProviderTest extends TestCase
{
    private HttpRankingProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new HttpRankingProvider($this->createStub(Client::class), $this->createStub(Config::class));
    }

    public function testValidateSanitizesUntrustedResponse(): void
    {
        $result = $this->provider->validate([
            'source' => 'hybrid<script>',
            'model_version' => 'ranker-v1; DROP',
            'recommendations' => [
                ['sku' => 'SKU-1002', 'score' => 0.93, 'reason_code' => 'Semantic_Similarity!'],
                ['sku' => 'SKU-2', 'score' => 7],
                ['sku' => ''],
                ['score' => 1],
                'garbage',
                ['sku' => ['array'], 'score' => 1],
            ],
        ]);
        $this->assertSame('hybridscript', $result['source']);
        $this->assertSame('ranker-v1DROP', $result['model_version']);
        $this->assertSame([
            ['sku' => 'SKU-1002', 'score' => 0.93, 'reason_code' => 'semantic_similarity'],
            ['sku' => 'SKU-2', 'score' => 1.0, 'reason_code' => ''],
        ], $result['recommendations']);
    }

    public function testValidateRejectsMissingList(): void
    {
        $this->expectException(LocalizedException::class);
        $this->provider->validate(['source' => 'x']);
    }
}
