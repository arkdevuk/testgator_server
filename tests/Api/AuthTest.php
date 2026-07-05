<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/auth/login and /api/auth/me.
 */
class AuthTest extends AbstractApiTestCase
{
    // ── POST /api/auth/login ──────────────────────────────────────────────

    public function testLoginWithValidCredentials(): void
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
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => 'wrongpassword',
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(403);
    }

    public function testLoginWithUnknownUser(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'nobody@testgator.test',
            'password' => 'irrelevant',
            'authMode' => 'app',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(403);
    }

    public function testLoginWithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
        ]);

        $this->assertStatusCode(400);
    }

    public function testLoginWithInvalidMode(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'app',
            'mode' => 'invalid_mode',
        ]);

        $this->assertStatusCode(400);
    }

    public function testLoginWithInvalidAuthMode(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::USER_EMAIL,
            'password' => TestFixtures::USER_PASSWORD,
            'authMode' => 'fakeauth',
            'mode' => 'team',
        ]);

        $this->assertStatusCode(400);
    }

    // ── GET /api/auth/me ──────────────────────────────────────────────────

    public function testMeReturnsAuthenticatedUser(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/auth/me', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::USER_EMAIL, $data['email']);
        self::assertArrayHasKey('roles', $data);
    }

    public function testMeWithTesterToken(): void
    {
        $token = $this->getTesterToken();
        $data = $this->jsonRequest('GET', '/api/auth/me', null, $token);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::TESTER_EMAIL, $data['email']);
    }

    public function testMeWithoutToken(): void
    {
        $this->jsonRequest('GET', '/api/auth/me');
        $this->assertStatusCode(401);
    }

    public function testMeWithInvalidToken(): void
    {
        $this->jsonRequest('GET', '/api/auth/me', null, 'not.a.valid.jwt');
        $this->assertStatusCode(401);
    }
}
