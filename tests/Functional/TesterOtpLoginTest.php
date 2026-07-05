<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserType;
use App\Tests\Api\AbstractApiTestCase;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Functional test — tester OTP login flow.
 *
 * Mirrors what the frontend does in auth.js → generateCode() + requestLogin():
 *
 *   Step 1 — Request OTP code
 *     POST /api/auth/login { username, password:'', authMode:'code', mode:'tester' }
 *     → { logged: false, otp: true }
 *
 *   Step 2 — Read the generated code directly from DB (no email in test env)
 *
 *   Step 3 — Submit the OTP code
 *     POST /api/auth/login { username, password:<code>, authMode:'app', mode:'tester' }
 *     → { logged: true, jwt, refreshToken }
 *
 *   Step 4 — GET /api/auth/me with the returned JWT
 *     → tester profile
 */
class TesterOtpLoginTest extends AbstractApiTestCase
{
    // ── Step 1: request OTP code ─────────────────────────────────────────────

    public function testRequestOtpCodeReturnsOtpFlag(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(200);
        self::assertFalse($data['logged']);
        self::assertTrue($data['otp']);
    }

    public function testRequestOtpCodeForUnknownTesterIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'ghost@testgator.test',
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);

        // Tester not found → 401
        $this->assertStatusCode(401);
    }

    // ── Full OTP flow ─────────────────────────────────────────────────────────

    public function testFullOtpFlowReturnsJwtAndMe(): void
    {
        // Step 1 — request OTP
        $step1 = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(200);
        self::assertFalse($step1['logged']);
        self::assertTrue($step1['otp']);

        // Step 2 — read the OTP directly from the DB (no real email in test env)
        static::$em->clear();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        self::assertNotNull($tester, 'Tester not found after OTP request');
        $otp = $tester->getOtp();
        self::assertNotEmpty($otp, 'OTP was not stored on the tester entity');

        // Step 3 — submit the OTP code
        $step3 = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => $otp,
            'authMode' => 'app',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(200);
        self::assertTrue($step3['logged']);
        self::assertArrayHasKey('jwt', $step3);
        self::assertNotEmpty($step3['jwt']);
        self::assertArrayHasKey('refreshToken', $step3);

        // Step 4 — GET /api/auth/me with the real tester JWT
        $me = $this->jsonRequest('GET', '/api/auth/me', null, $step3['jwt']);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::TESTER_EMAIL, $me['email']);
        self::assertArrayHasKey('roles', $me);
        self::assertContains('ROLE_TESTER', $me['roles']);
    }

    // ── Step 3 edge-cases ────────────────────────────────────────────────────

    public function testSubmitWrongOtpCodeIsRejected(): void
    {
        // Generate a real OTP first so the tester has one
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);
        $this->assertStatusCode(200);

        // Submit a wrong code
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '000000',
            'authMode' => 'app',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(403);
        self::assertFalse($data['logged']);
    }

    public function testSubmitOtpWithoutRequestingCodeFirst(): void
    {
        // No OTP has been generated — tester has no otp set
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '123456',
            'authMode' => 'app',
            'mode' => 'tester',
        ]);

        // Expired / no code
        $this->assertStatusCode(403);
        self::assertFalse($data['logged']);
    }

    public function testOtpIsInvalidatedAfterSuccessfulLogin(): void
    {
        // Step 1 — request OTP
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => '',
            'authMode' => 'code',
            'mode' => 'tester',
        ]);

        static::$em->clear();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);
        $otp = $tester->getOtp();

        // Step 2 — use the code successfully
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => $otp,
            'authMode' => 'app',
            'mode' => 'tester',
        ]);
        $this->assertStatusCode(200);

        // Step 3 — try to reuse the same code
        $replay = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL,
            'password' => $otp,
            'authMode' => 'app',
            'mode' => 'tester',
        ]);

        // Code was cleared on success, so this should fail
        $this->assertStatusCode(403);
        self::assertFalse($replay['logged']);
    }
}
