<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;

class LoggerTest extends TestCase
{
    public function testSecretsAndEmailsAreRedacted(): void
    {
        $clean = Logger::sanitize(
            'HTTP 401 Authorization: Bearer abc.def-123 key sk-proj_ABCDEFGH123 "api_key": "secret" for jane.doe@example.com'
        );
        $this->assertStringNotContainsString('abc.def-123', $clean);
        $this->assertStringNotContainsString('sk-proj_ABCDEFGH123', $clean);
        $this->assertStringNotContainsString('"secret"', $clean);
        $this->assertStringNotContainsString('jane.doe@example.com', $clean);
        $this->assertStringContainsString('HTTP 401', $clean);
    }

    public function testContextKeysAreRedacted(): void
    {
        $inner = $this->createMock(LoggerInterface::class);
        $inner->expects($this->once())->method('error')->with(
            'failed',
            $this->callback(static fn($context) => $context['api_key'] === '[redacted]'
                && $context['error'] === 'RuntimeException: boom [email]')
        );
        (new Logger($inner, $this->createStub(Config::class)))
            ->error('failed', ['api_key' => 'x', 'error' => new \RuntimeException('boom a@b.co')]);
    }
}
