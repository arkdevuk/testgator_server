<?php

declare(strict_types=1);

namespace App\Services\Security;

use Closure;

use const DNS_AAAA;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Guards against SSRF (server-side request forgery) for any URL that a
 * user can control and that the server will subsequently fetch — e.g. the
 * outbound webhook URL, which any ROLE_USER can set via Settings.
 *
 * assertSafe() checks, in order:
 *   1. Scheme must be http or https (blocks file://, gopher://, dict://, ...).
 *   2. The host must resolve to at least one IP address.
 *   3. None of the resolved IPs may be private (RFC1918), loopback,
 *      link-local, or otherwise reserved. Link-local (169.254.0.0/16) is
 *      what blocks cloud metadata endpoints such as 169.254.169.254.
 *
 * It returns the resolved IP alongside the URL so the caller can pin the
 * outbound connection to that exact address instead of letting the HTTP
 * client re-resolve the hostname — see ResolvedSafeUrl for why that
 * matters (DNS rebinding).
 */
class UrlSafetyChecker
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @var Closure(string):list<string>
     */
    private readonly Closure $resolver;

    /**
     * @param (callable(string): list<string>)|null $resolver override the
     *                                                        DNS resolver — used by tests to avoid real network lookups.
     *                                                        Defaults to real DNS resolution (A + AAAA records).
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver !== null ? Closure::fromCallable($resolver) : $this->resolveViaDns(...);
    }

    /**
     * @throws UnsafeUrlException if the URL is not safe to request
     */
    public function assertSafe(string $url): ResolvedSafeUrl
    {
        $parts = parse_url($url);

        // Scheme is checked before host: e.g. "file:///etc/passwd" parses with an
        // empty host, and we want that reported as "wrong scheme", not "malformed".
        if (false === $parts || !isset($parts['scheme'])) {
            throw new UnsafeUrlException('The URL is not well-formed.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeUrlException('Only http and https URLs are allowed.');
        }

        if (!isset($parts['host']) || '' === $parts['host']) {
            throw new UnsafeUrlException('The URL is not well-formed.');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ('https' === $scheme ? 443 : 80);

        $ips = $this->resolveIps($host);
        if ([] === $ips) {
            throw new UnsafeUrlException('The URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new UnsafeUrlException(sprintf('The URL resolves to a private, loopback, link-local, or reserved IP address (%s) and cannot be used.', $ip));
            }
        }

        return new ResolvedSafeUrl($url, $scheme, $host, $port, $ips[0]);
    }

    public function isSafe(string $url): bool
    {
        try {
            $this->assertSafe($url);

            return true;
        } catch (UnsafeUrlException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function resolveIps(string $host): array
    {
        // Literal IP in the URL (e.g. http://169.254.169.254/) — no DNS involved.
        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return ($this->resolver)($host);
    }

    /**
     * @return list<string>
     */
    private function resolveViaDns(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (false !== $v4) {
            $ips = [...$ips, ...$v4];
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    private function isPublicIp(string $ip): bool
    {
        return false !== filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
