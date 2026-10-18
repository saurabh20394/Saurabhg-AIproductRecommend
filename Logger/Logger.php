<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Logger;

use Psr\Log\LoggerInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Structured, sanitized module logger: secrets, tokens and e-mail addresses are redacted
 */
class Logger
{
    private const REDACTIONS = [
        '/Bearer\s+[A-Za-z0-9._\-~+\/=]+/i' => 'Bearer [redacted]',
        '/\b(sk|pk|rk)-[A-Za-z0-9_\-]{8,}/' => '[redacted-key]',
        '/("?(?:api[_-]?key|authorization|access[_-]?token|token|secret|password)"?\s*[:=]\s*)("[^"]*"|[^\s,}&]+)/i'
            => '$1"[redacted]"',
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i' => '[email]',
    ];

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $value
     * @return string
     */
    public static function sanitize(string $value): string
    {
        $value = (string)preg_replace(array_keys(self::REDACTIONS), array_values(self::REDACTIONS), $value);
        return mb_substr($value, 0, 2000);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error(self::sanitize($message), $this->sanitizeContext($context));
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning(self::sanitize($message), $this->sanitizeContext($context));
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info(self::sanitize($message), $this->sanitizeContext($context));
    }

    /**
     * Logged only when Debug Logging is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->config->isDebug()) {
            $this->logger->debug(self::sanitize($message), $this->sanitizeContext($context));
        }
    }

    /**
     * @param array $context
     * @return array
     */
    private function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (preg_match('/key|token|secret|password|authorization|email/i', (string)$key)) {
                $clean[$key] = '[redacted]';
            } elseif ($value instanceof \Throwable) {
                $clean[$key] = get_class($value) . ': ' . self::sanitize($value->getMessage());
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? self::sanitize($value) : $value;
            } else {
                $clean[$key] = self::sanitize((string)json_encode($value));
            }
        }
        return $clean;
    }
}
