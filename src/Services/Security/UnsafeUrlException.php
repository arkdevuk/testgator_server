<?php

declare(strict_types=1);

namespace App\Services\Security;

use RuntimeException;

/**
 * Thrown by UrlSafetyChecker when a user-supplied URL fails the SSRF
 * safety check: bad scheme, unresolvable host, or resolves to a private /
 * loopback / link-local / reserved IP address.
 *
 * The exception message is written to be safe to surface directly to the
 * user (e.g. as an API validation error) — it never echoes anything beyond
 * the offending IP/scheme.
 */
final class UnsafeUrlException extends RuntimeException
{
}
