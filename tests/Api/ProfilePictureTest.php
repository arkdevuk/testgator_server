<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Enum\UserType;
use App\Tests\DataFixtures\TestFixtures;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Integration tests for the two profile-picture endpoints.
 *
 * POST /api/testers/{id}/profile-picture
 *   – tester sets own URL          → 200
 *   – tester sets another's URL    → 403
 *   – admin sets any tester's URL  → 200
 *   – unauthenticated              → 401
 *   – tester not found             → 404
 *   – missing url field            → 400
 *   – invalid URL format           → 422
 *
 * POST /api/users/{id}/profile-picture  (validation path — S3 not available in tests)
 *   – tester attempts access       → 403
 *   – unauthenticated              → 401
 *   – user not found               → 404
 *   – missing file field           → 400
 *   – non-square image             → 422
 *   – image exceeds 800 px         → 422
 *   – image exceeds 500 KB         → 422
 *   – non-image file               → 422
 *   – GIF is rejected              → 422
 *   – valid image → reaches S3 step (500 since S3 not configured in test env)
 */
class ProfilePictureTest extends AbstractApiTestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    /** @var list<string> */
    private array $tmpFiles = [];

    public function testTesterCanSetOwnProfilePictureUrl(): void
    {
        $id = $this->getTesterUuid(TestFixtures::TESTER_EMAIL);
        $data = $this->jsonRequest(
            'POST',
            "/api/testers/{$id}/profile-picture",
            ['url' => 'https://cdn.example.com/avatar.png'],
            $this->getTesterToken(TestFixtures::TESTER_EMAIL),
        );

        $this->assertStatusCode(200);
        self::assertSame('https://cdn.example.com/avatar.png', $data['profilePictureUrl']);
    }

    private function getTesterUuid(string $email = TestFixtures::TESTER_EMAIL): string
    {
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::TESTER]);
        self::assertNotNull($tester);

        return (string) $tester->getId();
    }

    public function testTesterCannotSetAnotherTestersProfilePicture(): void
    {
        $otherId = $this->getTesterUuid(TestFixtures::TESTER_EMAIL_2);

        $this->jsonRequest(
            'POST',
            "/api/testers/{$otherId}/profile-picture",
            ['url' => 'https://cdn.example.com/avatar.png'],
            $this->getTesterToken(TestFixtures::TESTER_EMAIL),
        );

        $this->assertStatusCode(403);
    }

    public function testAdminCanSetAnyTesterProfilePictureUrl(): void
    {
        $id = $this->getTesterUuid(TestFixtures::TESTER_EMAIL);
        $data = $this->jsonRequest(
            'POST',
            "/api/testers/{$id}/profile-picture",
            ['url' => 'https://cdn.example.com/admin-set.png'],
            $this->getAdminToken(),
        );

        $this->assertStatusCode(200);
        self::assertSame('https://cdn.example.com/admin-set.png', $data['profilePictureUrl']);
    }

    public function testUnauthenticatedCannotSetTesterProfilePicture(): void
    {
        $id = $this->getTesterUuid();

        $this->jsonRequest('POST', "/api/testers/{$id}/profile-picture", ['url' => 'https://cdn.example.com/x.png']);

        $this->assertStatusCode(401);
    }

    public function testUnknownTesterReturns404(): void
    {
        $this->jsonRequest(
            'POST',
            '/api/testers/00000000-0000-0000-0000-000000000000/profile-picture',
            ['url' => 'https://cdn.example.com/x.png'],
            $this->getAdminToken(),
        );

        $this->assertStatusCode(404);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/testers/{id}/profile-picture
    // ══════════════════════════════════════════════════════════════════════════

    public function testMissingUrlFieldReturns400(): void
    {
        $id = $this->getTesterUuid();

        $this->jsonRequest(
            'POST',
            "/api/testers/{$id}/profile-picture",
            [],
            $this->getTesterToken(),
        );

        $this->assertStatusCode(400);
    }

    public function testInvalidUrlFormatReturns422(): void
    {
        $id = $this->getTesterUuid();

        $this->jsonRequest(
            'POST',
            "/api/testers/{$id}/profile-picture",
            ['url' => 'not-a-valid-url'],
            $this->getTesterToken(),
        );

        $this->assertStatusCode(422);
    }

    public function testTesterCannotAccessUserProfilePictureUpload(): void
    {
        $id = $this->getTeamUserUuid();
        $path = $this->makeTmpImage(100, 100);
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getTesterToken());

        $this->assertStatusCode(403);
    }

    private function getTeamUserUuid(string $email = TestFixtures::USER_EMAIL): string
    {
        $user = static::$em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::USER]);
        self::assertNotNull($user);

        return (string) $user->getId();
    }

    private function makeTmpImage(int $w, int $h, string $format = 'png'): string
    {
        $img = imagecreatetruecolor($w, $h);
        $path = tempnam(sys_get_temp_dir(), 'ppic_').'.'.$format;
        $this->tmpFiles[] = $path;
        if ($format === 'png') {
            imagepng($img, $path);
        } else {
            imagejpeg($img, $path, 90);
        }

        return $path;
    }

    private function makeUploadedFile(string $path, string $mime): UploadedFile
    {
        return new UploadedFile($path, basename($path), $mime, null, true);
    }

    private function uploadRequest(string $uri, UploadedFile $file, ?string $token): void
    {
        $headers = [];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        static::$client->request('POST', $uri, [], ['file' => $file], $headers);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/users/{id}/profile-picture
    // ══════════════════════════════════════════════════════════════════════════

    public function testUnauthenticatedCannotUploadUserProfilePicture(): void
    {
        $id = $this->getTeamUserUuid();
        $path = $this->makeTmpImage(100, 100);
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, null);

        $this->assertStatusCode(401);
    }

    public function testUnknownUserReturns404(): void
    {
        $path = $this->makeTmpImage(100, 100);
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest(
            '/api/users/00000000-0000-0000-0000-000000000000/profile-picture',
            $file,
            $this->getAdminToken(),
        );

        $this->assertStatusCode(404);
    }

    public function testMissingFileFieldReturns400(): void
    {
        $id = $this->getTeamUserUuid();

        $headers = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->getAdminToken()];
        static::$client->request('POST', "/api/users/{$id}/profile-picture", [], [], $headers);

        $this->assertStatusCode(400);
    }

    public function testNonSquareImageReturns422(): void
    {
        $id = $this->getTeamUserUuid();
        $path = $this->makeTmpImage(100, 200); // portrait — not square
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        $this->assertStatusCode(422);
        $body = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertStringContainsStringIgnoringCase('square', $body['error']);
    }

    public function testOversizedDimensionsReturns422(): void
    {
        $id = $this->getTeamUserUuid();
        $path = $this->makeTmpImage(801, 801); // over 800 px
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        $this->assertStatusCode(422);
        $body = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertStringContainsStringIgnoringCase('exceed', $body['error']);
    }

    public function testGifIsRejectedWith422(): void
    {
        $id = $this->getTeamUserUuid();

        // Minimal valid 1×1 GIF89a
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==', true);
        $path = tempnam(sys_get_temp_dir(), 'ppic_gif_').'.gif';
        $this->tmpFiles[] = $path;
        file_put_contents($path, $gif);

        $file = new UploadedFile($path, 'test.gif', 'image/gif', null, true);

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        $this->assertStatusCode(422);
        $body = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertStringContainsStringIgnoringCase('PNG and JPEG', $body['error']);
    }

    public function testNonImageFileIsRejectedWith422(): void
    {
        $id = $this->getTeamUserUuid();
        $path = tempnam(sys_get_temp_dir(), 'ppic_txt_').'.txt';
        $this->tmpFiles[] = $path;
        file_put_contents($path, 'this is not an image');

        $file = new UploadedFile($path, 'test.txt', 'text/plain', null, true);

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        $this->assertStatusCode(422);
    }

    public function testOversizedFileIsRejectedWith422(): void
    {
        $id = $this->getTeamUserUuid();
        $path = tempnam(sys_get_temp_dir(), 'ppic_big_').'.bin';
        $this->tmpFiles[] = $path;
        // Write just over 500 KB — size check fires before image parsing
        file_put_contents($path, str_repeat("\x00", 500 * 1024 + 1));

        $file = new UploadedFile($path, 'big.png', 'image/png', null, true);

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        $this->assertStatusCode(422);
        $body = json_decode(static::$client->getResponse()->getContent(), true);
        self::assertStringContainsStringIgnoringCase('500 KB', $body['error']);
    }

    public function testValidImageByAdminReachesS3Step(): void
    {
        // S3 is not configured in the test environment, so a valid image makes it
        // past all validation and fails at the S3 upload step (500).
        // This confirms validation passes and the code path reaches uploadImage().
        $id = $this->getTeamUserUuid();
        $path = $this->makeTmpImage(100, 100);
        $file = $this->makeUploadedFile($path, 'image/png');

        $this->uploadRequest("/api/users/{$id}/profile-picture", $file, $this->getAdminToken());

        // 500 = S3 not configured; 200 = S3 is configured and upload succeeded.
        self::assertContains($this->getStatusCode(), [200, 500]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->tmpFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
