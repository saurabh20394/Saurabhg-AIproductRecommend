<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider;

use Magento\Framework\App\CacheInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Circuit breaker for external AI dependencies; state is shared across requests through the cache
 */
class CircuitBreaker
{
    private const PREFIX = 'aipr_cb_';

    /**
     * @param CacheInterface $cache
     * @param Config $config
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $dependency
     * @return bool true while the dependency must not be called
     */
    public function isOpen(string $dependency): bool
    {
        $state = $this->getState($dependency);
        return ($state['open_until'] ?? 0) > time();
    }

    /**
     * @param string $dependency
     * @return void
     */
    public function recordSuccess(string $dependency): void
    {
        $state = $this->getState($dependency);
        if (($state['failures'] ?? 0) === 0 && ($state['last_success'] ?? 0) > time() - 60) {
            return;
        }
        $this->saveState($dependency, ['failures' => 0, 'open_until' => 0, 'last_success' => time()]);
    }

    /**
     * @param string $dependency
     * @param string $error sanitized error message
     * @return void
     */
    public function recordFailure(string $dependency, string $error): void
    {
        $state = $this->getState($dependency);
        $failures = (int)($state['failures'] ?? 0) + 1;
        $state['failures'] = $failures;
        $state['last_error'] = mb_substr($error, 0, 255);
        $state['last_failure'] = time();
        if ($failures >= max(1, $this->config->getInt('ai/circuit_threshold', null, 5))) {
            $state['open_until'] = time() + max(1, $this->config->getInt('ai/circuit_cooldown', null, 60));
        }
        $this->saveState($dependency, $state);
    }

    /**
     * @param string $dependency
     * @return array{failures?:int, open_until?:int, last_error?:string, last_failure?:int, last_success?:int}
     */
    public function getState(string $dependency): array
    {
        try {
            $raw = $this->cache->load(self::PREFIX . $dependency);
        } catch (\Throwable $e) {
            return [];
        }
        $state = $raw ? json_decode((string)$raw, true) : [];
        return is_array($state) ? $state : [];
    }

    /**
     * @param string $dependency
     * @param array $state
     * @return void
     */
    private function saveState(string $dependency, array $state): void
    {
        try {
            $this->cache->save((string)json_encode($state), self::PREFIX . $dependency, [], 86400);
        } catch (\Throwable $e) {
            // cache backend unavailable: breaker degrades to per-request behavior
            return;
        }
    }
}
