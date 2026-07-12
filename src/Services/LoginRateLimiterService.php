<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Fixed-window rate limiter for login endpoints.
 *
 * Keyed on (IP, username) so one user's lockout does not affect another.
 * Stored in the default cache pool with a TTL that matches the window.
 *
 * Defaults: 5 attempts per 15-minute window.
 */
class LoginRateLimiterService
{
    private const int MAX_ATTEMPTS = 5;
    private const int WINDOW_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Record a login attempt for the given (ip, username) pair and return
     * whether the attempt is allowed.
     *
     * @return array{allowed: bool, retryAfter: int}
     *                                               - allowed    : false when the limit is exceeded
     *                                               - retryAfter : seconds until the window resets (0 when allowed)
     */
    public function attempt(string $ip, string $username): array
    {
        $key = $this->cacheKey($ip, $username);
        $item = $this->cache->getItem($key);

        $data = $item->isHit() ? $item->get() : [
            'count' => 0,
            'reset_at' => time() + self::WINDOW_SECONDS,
        ];

        $retryAfter = max(0, $data['reset_at'] - time());

        if ($data['count'] >= self::MAX_ATTEMPTS) {
            return ['allowed' => false, 'retryAfter' => $retryAfter];
        }

        ++$data['count'];
        $item->set($data);
        $item->expiresAfter($retryAfter);
        $this->cache->save($item);

        return ['allowed' => true, 'retryAfter' => 0];
    }

    private function cacheKey(string $ip, string $username): string
    {
        // hash keeps the key safe for any cache backend and prevents
        // cache-key injection via a crafted username
        return 'login_rate_'.hash('sha256', $ip.':'.strtolower(trim($username)));
    }

    /**
     * Clear the counter for an (ip, username) pair on successful login,
     * so a legitimate user does not get blocked after recovering their password.
     */
    public function reset(string $ip, string $username): void
    {
        $this->cache->deleteItem($this->cacheKey($ip, $username));
    }
}
