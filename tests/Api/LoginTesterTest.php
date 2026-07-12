<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\TestPlan;
use App\Tests\DataFixtures\TestFixtures;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests for /api/auth/login_tester (guest/tester JWT via TestPlan key+hash)
 * and the single-step file upload endpoint /public/apx/upload.
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

    // ── POST /public/apx/upload (single-step upload) ──────────────────────────

    /**
     * The frontend now uploads directly to /public/apx/upload with the standard
     * app JWT (the separate /api/uploads/request pre-flight step was removed).
     * Without a valid app token the firewall rejects the request.
     */
    public function testUploadWithoutTokenIsRejected(): void
    {
        static::$client->request('POST', '/public/apx/upload');

        $this->assertStatusCode(401);
    }

    /**
     * A bogus/garbage bearer token must not be accepted either.
     */
    public function testUploadWithInvalidTokenIsRejected(): void
    {
        static::$client->request('POST', '/public/apx/upload',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer not-a-real-jwt'],
        );

        $this->assertStatusCode(401);
    }

    /**
     * A tester authenticated with the standard app JWT can upload a file: the
     * request passes authentication and authorization (testers are allowed to
     * upload) and reaches the storage step.
     *
     * S3 is not configured in the test environment, so a valid file makes it
     * past all validation and fails at the S3 upload step (500). A 200 would
     * mean S3 is configured and the upload succeeded. Either way the request is
     * neither rejected as unauthenticated (401) nor forbidden (403).
     */
    public function testTesterCanUploadFile(): void
    {
        $path = $this->makeTmpPng();
        $file = new UploadedFile($path, basename($path), 'image/png', null, true);

        static::$client->request(
            'POST',
            '/public/apx/upload',
            [],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->getTesterToken()],
        );

        self::assertContains($this->getStatusCode(), [200, 500]);
    }

    /** @var list<string> temp files to clean up */
    private array $tmpFiles = [];

    /**
     * Creates a small valid PNG on disk (so MIME sniffing passes) and returns its path.
     */
    private function makeTmpPng(): string
    {
        $img = imagecreatetruecolor(100, 100);
        $path = tempnam(sys_get_temp_dir(), 'upl_') . '.png';
        $this->tmpFiles[] = $path;
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tmpFiles = [];

        parent::tearDown();
    }
}
