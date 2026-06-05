<?php

namespace App\Tests\Api;

use App\Entity\TestPlan;
use App\Tests\DataFixtures\TestFixtures;

/**
 * Tests for /api/auth/login_tester (guest/tester JWT via TestPlan key+hash)
 * and /api/uploads/request
 */
class LoginTesterTest extends AbstractApiTestCase
{
    // ── POST /api/auth/login_tester ───────────────────────────────────────

    public function testLoginTesterRequiresChallengeHashAndTp(): void
    {
        $this->jsonRequest('POST', '/api/auth/login_tester', []);
        $this->assertStatusCode(400);
    }

    public function testLoginTesterWithInvalidTestPlan(): void
    {
        $this->jsonRequest('POST', '/api/auth/login_tester', [
            'challenge' => 'some-uuid',
            'hash' => 'somehash',
            'tp' => 99999,
        ]);
        $this->assertStatusCode(404);
    }

    public function testLoginTesterWithInvalidHash(): void
    {
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        $this->jsonRequest('POST', '/api/auth/login_tester', [
            'challenge' => TestFixtures::TESTER_EMAIL,
            'hash' => 'invalid-hash',
            'tp' => $plan->getId(),
        ]);

        $this->assertStatusCode(401);
    }

    public function testLoginTesterWithValidHash(): void
    {
        $plan = static::$em->getRepository(TestPlan::class)
            ->findOneBy(['name' => 'Published Plan']);

        // Build valid hash the same way GuestAuthService does
        $guestAuth = static::$container->get(\App\Services\Authentification\GuestAuthService::class);
        $challenge = TestFixtures::TESTER_EMAIL;
        $hash = $guestAuth->getHash($challenge, $plan->getKey());

        $data = $this->jsonRequest('POST', '/api/auth/login_tester', [
            'challenge' => $challenge,
            'hash' => $hash,
            'tp' => $plan->getId(),
        ]);

        $this->assertStatusCode(200);
        self::assertTrue($data['logged']);
        self::assertArrayHasKey('jwt', $data);
    }

    // ── GET /api/uploads/request ──────────────────────────────────────────

    public function testUploadRequestRequiresAuth(): void
    {
        static::$client->request('POST', '/api/uploads/request',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['filename' => 'test.png', 'size' => 1024])
        );
        $this->assertStatusCode(401);
    }

    public function testUploadRequestWithMissingPayload(): void
    {
        $token = $this->getTeamUserToken();
        static::$client->request('POST', '/api/uploads/request',
            [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([])
        );
        $this->assertStatusCode(400);
    }

    public function testUploadRequestWithValidPayload(): void
    {
        $token = $this->getTeamUserToken();
        static::$client->request('POST', '/api/uploads/request',
            [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode(['filename' => 'screenshot.png', 'size' => 512 * 1024])
        );

        $this->assertStatusCode(200);
        $data = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertArrayHasKey('jwt', $data);
        self::assertArrayHasKey('max_size', $data);
    }
}
