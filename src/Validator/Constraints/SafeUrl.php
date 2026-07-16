<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that a string is an http(s) URL which does not resolve to a
 * private, loopback, link-local, or otherwise reserved IP address.
 *
 * Used to stop SSRF via user-controlled outbound URLs (e.g. Settings'
 * webhook_url) — see App\Services\Security\UrlSafetyChecker for the checks
 * this delegates to.
 */
#[Attribute]
final class SafeUrl extends Constraint
{
    public string $message = 'This URL is not allowed: {{ reason }}';
}
