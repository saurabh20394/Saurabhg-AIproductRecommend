<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Event;

use Magento\Framework\App\CacheInterface;

/**
 * Fixed-window rate limiter for public endpoints (per visitor / IP, per minute)
 */
class RateLimiter
{
    /**
     * @param CacheInterface $cache
     */
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Consume $cost units; false when the limit for the current minute is exceeded
     *
     * @param string $key
     * @param int $cost
     * @param int $limit
     * @return bool
     */
    public function consume(string $key, int $cost, int $limit): bool
    {
        $cacheKey = 'aipr_rl_' . sha1($key) . '_' . (int)floor(time() / 60);
        try {
            $used = (int)$this->cache->load($cacheKey);
            if ($used + $cost > $limit) {
                return false;
            }
            $this->cache->save((string)($used + $cost), $cacheKey, [], 120);
        } catch (\Throwable $e) {
            // cache unavailable: fail open so tracking never blocks the storefront
            return true;
        }
        return true;
    }
}
