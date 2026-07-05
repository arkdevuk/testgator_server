<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Services\LoginRateLimiterService;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Integration tests for the login rate limiter.
 *
 * Each test resets known rate-limit counters in setUp() so tests are order-
 * independent and do not bleed state into AuthTest or each other.
 *
 * Window: 5 attempts per 15 min (configured in LoginRateLimiterService).
 * The test client always sends requests from 127.0.0.1.
 */
class LoginRateLimitTest extends AbstractApiTestCase
{
    private const WRONG_PASSWORD = '__wrong_password__';

    private LoginRateLimiterService $rateLimiter;

    public function testValidTeamLoginUnaffected(): void
    {
        $data = $this->login(TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->assertStatusCode(200);
        self::assertTrue($data['logged']);
        self::assertArrayHasKey('jwt', $data);
    }

    // ── Regression: existing auth flow must still pass ────────────────────────

    private function login(
        string $username,
        string $password,
        string $mode = 'team',
        string $authMode = 'app',
    ): array
    {
        return $this->jsonRequest('POST', '/api/auth/login', [
            'username' => $username,
            'password' => $password,
            'authMode' => $authMode,
            'mode' => $mode,
        ]);
    }

    public function testWrongPasswordStillReturns403(): void
    {
        $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        $this->assertStatusCode(403);
    }

    public function testMissingFieldsStillReturns400(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['username' => TestFixtures::USER_EMAIL]);
        $this->assertStatusCode(400);
    }

    // ── Rate limit enforcement ────────────────────────────────────────────────

    public function testFiveFailedAttemptsAreAllAllowed(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
            self::assertNotSame(
                429,
                $this->getStatusCode(),
                "Attempt {$i} should not be rate-limited (limit is 5).",
            );
        }
    }

    public function testSixthAttemptIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }

        $data = $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);

        $this->assertStatusCode(429);
        self::assertFalse($data['logged']);
    }

    public function testRateLimitAppliesEvenWithCorrectPassword(): void
    {
        // Exhaust the window with wrong passwords…
        for ($i = 0; $i < 5; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }

        // …then try the correct one: still blocked.
        $data = $this->login(TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->assertStatusCode(429);
        self::assertFalse($data['logged']);
    }

    public function testRetryAfterHeaderPresentOn429(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }

        $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);

        $this->assertStatusCode(429);
        $retryAfter = static::$client->getResponse()->headers->get('Retry-After');
        self::assertNotNull($retryAfter, 'Retry-After header must be present on 429.');
        self::assertGreaterThan(0, (int)$retryAfter);
    }

    // ── Reset on success ──────────────────────────────────────────────────────

    public function testSuccessfulLoginResetsWindowCounter(): void
    {
        // Use 4 of the 5 allowed slots.
        for ($i = 0; $i < 4; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }

        // Successful login resets the counter.
        $this->login(TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);
        $this->assertStatusCode(200);

        // A fresh window of 5 should now be available — 5th attempt = allowed.
        for ($i = 0; $i < 4; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }
        $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);

        // Still on attempt 5 of the new window → 403 (wrong password), NOT 429.
        $this->assertStatusCode(403);
    }

    // ── OTP / code flow exemption ─────────────────────────────────────────────

    public function testOtpFlowBypassesRateLimit(): void
    {
        // Exhaust the window for tester's username.
        for ($i = 0; $i < 5; ++$i) {
            $this->login(TestFixtures::TESTER_EMAIL, self::WRONG_PASSWORD, 'tester');
        }

        // authMode=code must still work — it skips the rate-limit check.
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(200);
        self::assertTrue($data['otp'] ?? false, 'OTP flow should return otp=true even when app-mode window is exhausted.');
    }

    // ── Isolation between users ───────────────────────────────────────────────

    public function testLockingOneUserDoesNotAffectAnother(): void
    {
        // Lock out USER_EMAIL.
        for ($i = 0; $i < 5; ++$i) {
            $this->login(TestFixtures::USER_EMAIL, self::WRONG_PASSWORD);
        }

        // TESTER_EMAIL should still have a fresh window: 403, not 429.
        $this->login(TestFixtures::TESTER_EMAIL, self::WRONG_PASSWORD, 'tester');
        $this->assertStatusCode(403);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = static::$container->get(LoginRateLimiterService::class);

        // Prevent state from prior tests from polluting this test's window.
        $this->rateLimiter->reset('127.0.0.1', TestFixtures::USER_EMAIL);
        $this->rateLimiter->reset('127.0.0.1', TestFixtures::TESTER_EMAIL);
        $this->rateLimiter->reset('127.0.0.1', TestFixtures::TESTER_EMAIL_2);
    }
}
