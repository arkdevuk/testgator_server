<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Api\AbstractApiTestCase;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Functional test — dev-team (ROLE_USER) login flow.
 *
 * Mirrors what the frontend does in auth.js → requestLogin():
 *   1. POST /api/auth/login  { username, password, authMode:'app', mode:'team' }
 *   2. GET  /api/auth/me     (with the returned JWT)
 */
class DevTeamLoginTest extends AbstractApiTestCase
{
    // ── Step 1: POST /api/auth/login ─────────────────────────────────────────

    public function testLoginReturnsJwtAndRefreshToken(): void
    {
        $data = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(200);
        self::assertTrue($data['logged']);
        self::assertArrayHasKey('jwt', $data);
        self::assertNotEmpty($data['jwt']);
        self::assertArrayHasKey('refreshToken', $data);
        self::assertNotEmpty($data['refreshToken']);
        self::assertSame('db', $data['authMode']);
    }

    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => 'WrongPassword99!',
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(403);
    }

    public function testLoginWithUnknownEmailIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'nobody@testgator.test',
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(403);
    }

    public function testLoginWithMissingPasswordIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(400);
    }

    public function testLoginWithInvalidModeIsRejected(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'app',
            'mode' => 'unknown_mode',
        ]);

        $this->assertStatusCode(400);
    }

    // ── Step 2: GET /api/auth/me ─────────────────────────────────────────────

    public function testMeReturnsCorrectUserAfterLogin(): void
    {
        // Step 1 — login
        $loginData = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(200);
        $jwt = $loginData['jwt'];

        // Step 2 — GET /api/auth/me with the real JWT
        $me = $this->jsonRequest('GET', '/api/auth/me', null, $jwt);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::USER_EMAIL, $me['email']);
        self::assertArrayHasKey('roles', $me);
        self::assertContains('ROLE_USER', $me['roles']);
    }

    public function testMeWithAdminReturnsAdminRoles(): void
    {
        $loginData = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::ADMIN_EMAIL,
            'password' => TestFixtures::ADMIN_PASSWORD,
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(200);
        $jwt = $loginData['jwt'];

        $me = $this->jsonRequest('GET', '/api/auth/me', null, $jwt);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::ADMIN_EMAIL, $me['email']);
        self::assertContains('ROLE_ADMIN', $me['roles']);
    }

    public function testMeWithoutTokenIsUnauthorized(): void
    {
        $this->jsonRequest('GET', '/api/auth/me');
        $this->assertStatusCode(401);
    }

    public function testMeWithInvalidTokenIsUnauthorized(): void
    {
        $this->jsonRequest('GET', '/api/auth/me', null, 'not.a.valid.jwt');
        $this->assertStatusCode(401);
    }

    public function testMeWithExpiredOrTamperedTokenIsUnauthorized(): void
    {
        // Garbled signature — valid base64 structure but wrong signature
        $tampered = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9'
            .'.eyJlbWFpbCI6ImZha2VAZXhhbXBsZS5jb20ifQ'
            .'.invalidsignature';

        $this->jsonRequest('GET', '/api/auth/me', null, $tampered);
        $this->assertStatusCode(401);
    }
}
