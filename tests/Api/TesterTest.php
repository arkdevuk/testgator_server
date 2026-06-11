<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Enum\UserType;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/testers
 */
class TesterTest extends AbstractApiTestCase
{
    // ── GET /api/testers ──────────────────────────────────────────────────

    public function testListTestersRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/testers');
        $this->assertStatusCode(401);
    }

    public function testListTestersReturnsCollection(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('GET', '/api/testers', null, $token);

        $this->assertStatusCode(200);
        $this->assertJsonKey('member', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testFilterTesterByEmail(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest(
            'GET',
            '/api/testers?email=' . urlencode('tester1'),
            null,
            $token
        );

        $this->assertStatusCode(200);
        self::assertGreaterThan(0, $data['totalItems']);
        foreach ($data['member'] as $t) {
            self::assertStringContainsString('tester1', $t['email']);
        }
    }

    public function testFilterTesterByActive(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest(
            'GET',
            '/api/testers?active=true',
            null,
            $token
        );

        $this->assertStatusCode(200);
        foreach ($data['member'] as $t) {
            self::assertTrue($t['active']);
        }
    }

    // ── GET /api/testers/{id} ─────────────────────────────────────────────

    public function testGetTester(): void
    {
        $token = $this->getTeamUserToken();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL, 'type' => UserType::TESTER]);

        $data = $this->jsonRequest('GET', '/api/testers/' . $tester->getId(), null, $token);

        $this->assertStatusCode(200);
        self::assertSame(TestFixtures::TESTER_EMAIL, $data['email']);
        $this->assertJsonKey('active', $data);
        $this->assertJsonKey('activeProjects', $data);
    }

    // ── POST /api/testers ─────────────────────────────────────────────────

    public function testCreateTester(): void
    {
        $token = $this->getTeamUserToken();
        $data = $this->jsonRequest('POST', '/api/testers', [
            'email' => 'newtester@testgator.test',
            'active' => true,
        ], $token);

        $this->assertStatusCode(201);
        self::assertSame('newtester@testgator.test', $data['email']);
        self::assertTrue($data['active']);
    }

    public function testCreateTesterRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/testers', [
            'email' => 'fail@testgator.test',
            'active' => true,
        ]);
        $this->assertStatusCode(401);
    }

    // ── PATCH /api/testers/{id} ───────────────────────────────────────────

    public function testDeactivateTester(): void
    {
        $token = $this->getTeamUserToken();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL_2]);

        static::$client->request('PATCH', '/api/testers/' . $tester->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['active' => false])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertFalse($data['active']);
    }

    // ── DELETE /api/testers/{id} ──────────────────────────────────────────

    public function testDeleteTester(): void
    {
        $token = $this->getTeamUserToken();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL_2]);

        static::$client->request('DELETE', '/api/testers/' . $tester->getId(),
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertStatusCode(204);
    }

    // ── Tester login flow ─────────────────────────────────────────────────

    public function testInactiveTesterCannotLogin(): void
    {
        $token = $this->getTeamUserToken();
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::TESTER_EMAIL_2]);

        // Deactivate
        static::$client->request('PATCH', '/api/testers/' . $tester->getId(),
            [], [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['active' => false])
        );

        // Try to login
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => TestFixtures::TESTER_EMAIL_2,
            'password' => 'anything',
            'authMode' => 'app',
            'mode' => 'tester',
        ]);

        $this->assertStatusCode(401);
    }
}
