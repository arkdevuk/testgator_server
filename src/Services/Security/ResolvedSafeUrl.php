<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * A URL that has passed UrlSafetyChecker::assertSafe(), together with the
 * exact IP address it resolved to at check time.
 *
 * Callers that go on to make an HTTP request should pin the connection to
 * this IP (e.g. curl's CURLOPT_RESOLVE) rather than letting the HTTP client
 * re-resolve the hostname. Re-resolving would reopen a DNS-rebinding gap:
 * an attacker-controlled domain can resolve to a public IP at check time
 * and to an internal/metadata IP a moment later.
 */
final readonly class ResolvedSafeUrl
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public string $ip,
    ) {
    }
}
