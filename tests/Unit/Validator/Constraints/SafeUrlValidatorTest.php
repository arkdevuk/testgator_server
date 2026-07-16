<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Constraints;

use App\Services\Security\UrlSafetyChecker;
use App\Validator\Constraints\SafeUrl;
use App\Validator\Constraints\SafeUrlValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Unit tests for SafeUrlValidator — confirms an unsafe URL surfaces as a
 * validation violation (i.e. a 422 from the API) instead of failing
 * silently, which is what wires UrlSafetyChecker into the Settings API.
 */
class SafeUrlValidatorTest extends ConstraintValidatorTestCase
{
    private UrlSafetyChecker $checker;

    protected function createValidator(): SafeUrlValidator
    {
        return new SafeUrlValidator($this->checker);
    }

    protected function setUp(): void
    {
        // Default: DNS resolves to a public IP, i.e. URLs are safe unless a
        // test overrides $this->checker before calling the validator again.
        $this->checker = new UrlSafetyChecker(static fn (string $host): array => ['93.184.216.34']);

        parent::setUp();
    }

    public function testNullValueIsValid(): void
    {
        $this->validator->validate(null, new SafeUrl());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new SafeUrl());

        $this->assertNoViolation();
    }

    public function testSafePublicUrlIsValid(): void
    {
        $this->validator->validate('https://example.com/hook', new SafeUrl());

        $this->assertNoViolation();
    }

    public function testUrlResolvingToPrivateIpRaisesViolation(): void
    {
        $checker = new UrlSafetyChecker(static fn (string $host): array => ['10.0.0.5']);
        $validator = new SafeUrlValidator($checker);
        $validator->initialize($this->context);

        $constraint = new SafeUrl();
        $validator->validate('https://internal.example.com/hook', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter(
                '{{ reason }}',
                'The URL resolves to a private, loopback, link-local, or reserved IP address (10.0.0.5) and cannot be used.',
            )
            ->assertRaised();
    }

    public function testLiteralMetadataIpRaisesViolation(): void
    {
        $constraint = new SafeUrl();

        $this->validator->validate('http://169.254.169.254/latest/meta-data/', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter(
                '{{ reason }}',
                'The URL resolves to a private, loopback, link-local, or reserved IP address (169.254.169.254) and cannot be used.',
            )
            ->assertRaised();
    }

    public function testNonHttpSchemeRaisesViolation(): void
    {
        $constraint = new SafeUrl();

        $this->validator->validate('file:///etc/passwd', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ reason }}', 'Only http and https URLs are allowed.')
            ->assertRaised();
    }
}
