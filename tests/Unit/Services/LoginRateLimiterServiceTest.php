<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\LoginRateLimiterService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Unit tests for LoginRateLimiterService.
 *
 * All cache interactions are mocked — no I/O, no Symfony kernel.
 */
class LoginRateLimiterServiceTest extends TestCase
{
    private CacheItemPoolInterface $cache;
    private LoginRateLimiterService $service;

    public function testFirstAttemptIsAllowed(): void
    {
        $item = $this->makeItem(isHit: false);
        $this->cache->method('getItem')->willReturn($item);
        $this->cache->method('save')->willReturn(true);

        $result = $this->service->attempt('127.0.0.1', 'user@test.com');

        self::assertTrue($result['allowed']);
        self::assertSame(0, $result['retryAfter']);
    }

    // ── attempt() ────────────────────────────────────────────────────────────

    private function makeItem(bool $isHit, array $data = []): CacheItemInterface
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn($isHit);

        if ($isHit) {
            $item->method('get')->willReturn($data);
        }

        // Allow set/expiresAfter to be called on a non-blocked item
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        return $item;
    }

    public function testFifthAttemptIsStillAllowed(): void
    {
        // 4 previous attempts stored → this is the 5th
        $item = $this->makeItem(isHit: true, data: ['count' => 4, 'reset_at' => time() + 600]);
        $this->cache->method('getItem')->willReturn($item);
        $this->cache->method('save')->willReturn(true);

        $result = $this->service->attempt('127.0.0.1', 'user@test.com');

        self::assertTrue($result['allowed']);
    }

    public function testSixthAttemptIsBlocked(): void
    {
        // Counter is already at the limit
        $item = $this->makeItem(isHit: true, data: ['count' => 5, 'reset_at' => time() + 600]);
        $this->cache->method('getItem')->willReturn($item);

        $result = $this->service->attempt('127.0.0.1', 'user@test.com');

        self::assertFalse($result['allowed']);
        self::assertGreaterThan(0, $result['retryAfter']);
    }

    public function testRetryAfterMatchesRemainingWindowTime(): void
    {
        $resetAt = time() + 300;
        $item = $this->makeItem(isHit: true, data: ['count' => 5, 'reset_at' => $resetAt]);
        $this->cache->method('getItem')->willReturn($item);

        $result = $this->service->attempt('127.0.0.1', 'user@test.com');

        self::assertFalse($result['allowed']);
        // Allow ±2 s for execution time
        self::assertEqualsWithDelta(300, $result['retryAfter'], 2);
    }

    public function testDifferentUsersHaveIndependentCounters(): void
    {
        $blockedItem = $this->makeItem(isHit: true, data: ['count' => 5, 'reset_at' => time() + 600]);
        $freshItem = $this->makeItem(isHit: false);

        $callCount = 0;
        $this->cache->method('getItem')
            ->willReturnCallback(function () use (&$callCount, $blockedItem, $freshItem) {
                return $callCount++ === 0 ? $blockedItem : $freshItem;
            });
        $this->cache->method('save')->willReturn(true);

        $resultA = $this->service->attempt('127.0.0.1', 'blocked@test.com');
        $resultB = $this->service->attempt('127.0.0.1', 'other@test.com');

        self::assertFalse($resultA['allowed'], 'blocked user should be denied');
        self::assertTrue($resultB['allowed'], 'other user should be allowed');
    }

    public function testSameUserDifferentIpHasIndependentCounter(): void
    {
        $blockedItem = $this->makeItem(isHit: true, data: ['count' => 5, 'reset_at' => time() + 600]);
        $freshItem = $this->makeItem(isHit: false);

        $callCount = 0;
        $this->cache->method('getItem')
            ->willReturnCallback(function () use (&$callCount, $blockedItem, $freshItem) {
                return $callCount++ === 0 ? $blockedItem : $freshItem;
            });
        $this->cache->method('save')->willReturn(true);

        $resultA = $this->service->attempt('1.2.3.4', 'user@test.com');
        $resultB = $this->service->attempt('9.9.9.9', 'user@test.com');

        self::assertFalse($resultA['allowed']);
        self::assertTrue($resultB['allowed']);
    }

    public function testBlockedRequestDoesNotSaveToCache(): void
    {
        $item = $this->makeItem(isHit: true, data: ['count' => 5, 'reset_at' => time() + 600]);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        // save() must NOT be called when the request is rejected
        $cache->expects(self::never())->method('save');

        $service = new LoginRateLimiterService($cache);
        $service->attempt('127.0.0.1', 'user@test.com');
    }

    // ── reset() ──────────────────────────────────────────────────────────────

    public function testResetDeletesCacheItem(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects(self::once())
            ->method('deleteItem')
            ->with(self::stringStartsWith('login_rate_'));

        $service = new LoginRateLimiterService($cache);
        $service->reset('127.0.0.1', 'user@test.com');
    }

    public function testResetKeyDependsOnIpAndUsername(): void
    {
        $capturedKeys = [];
        $this->cache->method('deleteItem')
            ->willReturnCallback(function (string $key) use (&$capturedKeys) {
                $capturedKeys[] = $key;

                return true;
            });

        $this->service->reset('1.1.1.1', 'alice@test.com');
        $this->service->reset('2.2.2.2', 'alice@test.com');
        $this->service->reset('1.1.1.1', 'bob@test.com');

        // All three combinations must produce distinct cache keys
        self::assertCount(3, array_unique($capturedKeys), 'Each (ip, username) pair must produce a unique cache key');
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Most tests use the pool purely as a stub (canned return values). The two
        // tests that assert on calls (save / deleteItem) build their own mock.
        $this->cache = $this->createStub(CacheItemPoolInterface::class);
        $this->service = new LoginRateLimiterService($this->cache);
    }
}
