<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Model\Recommendation;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;
use Saurabhg\AIproductRecommend\Model\Recommendation\TrackingToken;

class TrackingTokenTest extends TestCase
{
    private TrackingToken $token;

    protected function setUp(): void
    {
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturn('test-crypt-key');
        $this->token = new TrackingToken($config);
    }

    public function testRoundTrip(): void
    {
        $decoded = $this->token->decode($this->token->encode('pdp_similar', 'abc123', 1, 4, 9));
        $this->assertSame(
            ['unit_code' => 'pdp_similar', 'request_id' => 'abc123', 'store_id' => 1, 'experiment_id' => 4, 'variant_id' => 9],
            $decoded
        );
        $this->assertNull($this->token->decode($this->token->encode('home', 'r', 1, null, null))['experiment_id']);
    }

    public function testTamperedOrMalformedTokensAreRejected(): void
    {
        $token = $this->token->encode('pdp_similar', 'abc123', 1, 4, 9);
        [$payload, $signature] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode(json_encode(['u' => 'pdp_similar', 'r' => 'x', 's' => 1, 'e' => 4, 'v' => 10, 't' => time()])), '+/', '-_'), '=');
        $this->assertNull($this->token->decode($forged . '.' . $signature));
        $this->assertNull($this->token->decode($payload . '.deadbeef'));
        $this->assertNull($this->token->decode('nodot'));
        $this->assertNull($this->token->decode(null));
    }
}
