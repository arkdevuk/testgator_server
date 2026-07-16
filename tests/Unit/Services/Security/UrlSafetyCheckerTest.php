<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Security;

use App\Services\Security\ResolvedSafeUrl;
use App\Services\Security\UnsafeUrlException;
use App\Services\Security\UrlSafetyChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UrlSafetyChecker — the SSRF guard used both by the
 * Settings "webhook_url" validator and by WebhookService at dispatch time.
 *
 * DNS is mocked via the resolver callback so these tests never touch the
 * network and are deterministic.
 */
class UrlSafetyCheckerTest extends TestCase
{
    // ── scheme checks ───────────────────────────────────────────────────────

    public function testHttpsUrlWithPublicIpIsSafe(): void
    {
        $checker = $this->makeChecker(['93.184.216.34']);

        $resolved = $checker->assertSafe('https://example.com/hook');

        self::assertInstanceOf(ResolvedSafeUrl::class, $resolved);
        self::assertSame('93.184.216.34', $resolved->ip);
        self::assertSame('example.com', $resolved->host);
        self::assertSame(443, $resolved->port);
        self::assertSame('https', $resolved->scheme);
    }

    public function testHttpUrlWithPublicIpIsSafe(): void
    {
        $checker = $this->makeChecker(['93.184.216.34']);

        $resolved = $checker->assertSafe('http://example.com/hook');

        self::assertSame(80, $resolved->port);
    }

    public function testExplicitPortIsPreserved(): void
    {
        $checker = $this->makeChecker(['93.184.216.34']);

        $resolved = $checker->assertSafe('https://example.com:8443/hook');

        self::assertSame(8443, $resolved->port);
    }

    #[DataProvider('disallowedSchemes')]
    public function testNonHttpSchemesAreRejected(string $url): void
    {
        $checker = $this->makeChecker(['93.184.216.34']);

        $this->expectException(UnsafeUrlException::class);
        $this->expectExceptionMessage('Only http and https URLs are allowed.');

        $checker->assertSafe($url);
    }

    public static function disallowedSchemes(): array
    {
        return [
            'ftp' => ['ftp://example.com/hook'],
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://example.com/hook'],
            'dict' => ['dict://example.com/hook'],
        ];
    }

    public function testMalformedUrlIsRejected(): void
    {
        $checker = $this->makeChecker(['93.184.216.34']);

        $this->expectException(UnsafeUrlException::class);

        $checker->assertSafe('not-a-url');
    }

    // ── private / reserved IP checks ────────────────────────────────────────

    #[DataProvider('unsafeIps')]
    public function testUrlsResolvingToUnsafeIpsAreRejected(string $ip): void
    {
        $checker = $this->makeChecker([$ip]);

        $this->expectException(UnsafeUrlException::class);

        $checker->assertSafe('https://example.com/hook');
    }

    public static function unsafeIps(): array
    {
        return [
            'loopback v4' => ['127.0.0.1'],
            'link-local / cloud metadata' => ['169.254.169.254'],
            'rfc1918 10/8' => ['10.0.0.5'],
            'rfc1918 172.16/12' => ['172.16.5.5'],
            'rfc1918 192.168/16' => ['192.168.1.1'],
            'loopback v6' => ['::1'],
            'unique local v6' => ['fd00::1'],
            'ipv4-mapped v6 metadata' => ['::ffff:169.254.169.254'],
        ];
    }

    public function testLiteralPrivateIpInUrlIsRejectedWithoutCallingResolver(): void
    {
        $resolverCalled = false;
        $checker = new UrlSafetyChecker(static function () use (&$resolverCalled) {
            $resolverCalled = true;

            return ['93.184.216.34'];
        });

        $this->expectException(UnsafeUrlException::class);

        try {
            $checker->assertSafe('http://127.0.0.1/latest/meta-data/');
        } finally {
            self::assertFalse($resolverCalled, 'a literal IP host must not trigger a DNS lookup');
        }
    }

    public function testLiteralPublicIpInUrlIsSafe(): void
    {
        $checker = $this->makeChecker([]);

        $resolved = $checker->assertSafe('http://93.184.216.34/hook');

        self::assertSame('93.184.216.34', $resolved->ip);
    }

    public function testOneUnsafeIpAmongMultipleAnswersRejectsTheWholeUrl(): void
    {
        // Simulates a DNS-rebinding-style multi-answer response: one public,
        // one internal. All resolved addresses must be safe, not just one.
        $checker = $this->makeChecker(['93.184.216.34', '169.254.169.254']);

        $this->expectException(UnsafeUrlException::class);

        $checker->assertSafe('https://example.com/hook');
    }

    public function testUnresolvableHostIsRejected(): void
    {
        $checker = $this->makeChecker([]);

        $this->expectException(UnsafeUrlException::class);
        $this->expectExceptionMessage('The URL host could not be resolved.');

        $checker->assertSafe('https://this-domain-does-not-exist.invalid/hook');
    }

    // ── isSafe() ─────────────────────────────────────────────────────────────

    public function testIsSafeReturnsBooleanInsteadOfThrowing(): void
    {
        $safeChecker = $this->makeChecker(['93.184.216.34']);
        $unsafeChecker = $this->makeChecker(['127.0.0.1']);

        self::assertTrue($safeChecker->isSafe('https://example.com/hook'));
        self::assertFalse($unsafeChecker->isSafe('https://example.com/hook'));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeChecker(array $resolvedIps): UrlSafetyChecker
    {
        return new UrlSafetyChecker(static fn (string $host): array => $resolvedIps);
    }
}
