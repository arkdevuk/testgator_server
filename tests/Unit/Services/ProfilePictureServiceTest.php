<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\FileService;
use App\Services\ProfilePictureService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProfilePictureService.
 *
 * Validation tests use temporary image files generated via GD — no I/O to S3
 * or DB. Upload tests assert that storage is delegated to FileService.
 */
class ProfilePictureServiceTest extends TestCase
{
    private ProfilePictureService $service;

    /** @var list<string> temp files to clean up */
    private array $tmpFiles = [];

    public function testValidSquarePngReturnsCorrectMime(): void
    {
        $path = $this->makeTmpImage(100, 100, 'png');
        $mime = $this->service->validateImage($path);
        self::assertSame('image/png', $mime);
    }

    /**
     * Creates a temporary PNG or JPEG at the given dimensions and returns its path.
     */
    private function makeTmpImage(int $w, int $h, string $format = 'png', int $quality = 9): string
    {
        $img = imagecreatetruecolor($w, $h);
        $path = tempnam(sys_get_temp_dir(), 'ppic_') . '.' . $format;
        $this->tmpFiles[] = $path;

        if ($format === 'png') {
            imagepng($img, $path);
        } else {
            imagejpeg($img, $path, 90);
        }

        imagedestroy($img);

        return $path;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function testValidSquareJpegReturnsCorrectMime(): void
    {
        $path = $this->makeTmpImage(200, 200, 'jpeg');
        $mime = $this->service->validateImage($path);
        self::assertSame('image/jpeg', $mime);
    }

    public function testMaxAllowedDimensionIsAccepted(): void
    {
        $path = $this->makeTmpImage(ProfilePictureService::MAX_PX, ProfilePictureService::MAX_PX);
        $mime = $this->service->validateImage($path);
        self::assertSame('image/png', $mime);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function testFileOverMaxBytesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/less than 500 KB/i');

        // Build a valid but bloated PNG by injecting padding after the header.
        // Simplest approach: just write a raw file larger than MAX_BYTES.
        $path = tempnam(sys_get_temp_dir(), 'ppic_big_') . '.bin';
        $this->tmpFiles[] = $path;
        file_put_contents($path, str_repeat('A', ProfilePictureService::MAX_BYTES + 1));

        $this->service->validateImage($path);
    }

    public function testImageExceedingMaxWidthIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not exceed/i');

        $path = $this->makeTmpImage(ProfilePictureService::MAX_PX + 1, ProfilePictureService::MAX_PX + 1);
        $this->service->validateImage($path);
    }

    public function testNonSquareImageIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/square/i');

        $path = $this->makeTmpImage(100, 200);
        $this->service->validateImage($path);
    }

    // ── Size violations ───────────────────────────────────────────────────────

    public function testNonSquareLandscapeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/square/i');

        $path = $this->makeTmpImage(300, 200);
        $this->service->validateImage($path);
    }

    // ── Dimension violations ──────────────────────────────────────────────────

    public function testNonImageFileIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $path = tempnam(sys_get_temp_dir(), 'ppic_txt_');
        $this->tmpFiles[] = $path;
        file_put_contents($path, 'this is not an image');

        $this->service->validateImage($path);
    }

    public function testGifIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Only PNG and JPEG/i');

        // Minimal 1×1 GIF89a
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==', true);
        $path = tempnam(sys_get_temp_dir(), 'ppic_gif_') . '.gif';
        $this->tmpFiles[] = $path;
        file_put_contents($path, $gif);

        $this->service->validateImage($path);
    }

    protected function setUp(): void
    {
        // Validation tests do no S3 I/O, so a stub FileService is enough here.
        // The delegation tests below build their own mock with expectations.
        $this->service = new ProfilePictureService($this->createStub(FileService::class));
    }

    // ── Upload delegation ─────────────────────────────────────────────────────

    public function testUploadPublicImageDelegatesToFileService(): void
    {
        $fileService = $this->createMock(FileService::class);
        $fileService
            ->expects(self::once())
            ->method('putPublicObject')
            ->with('/tmp/source.png', 'image/png', 'project-banners/42.png')
            ->willReturn('https://cdn.example/project-banners/42.png');

        $service = new ProfilePictureService($fileService);

        $url = $service->uploadPublicImage('/tmp/source.png', 'image/png', 'project-banners/42.png');

        self::assertSame('https://cdn.example/project-banners/42.png', $url);
    }

    public function testUploadImageBuildsProfilePictureKeyAndDelegates(): void
    {
        $uuid = '019e0000-0000-7000-8000-000000000000';
        $expectedKey = 'profile-pictures/' . $uuid . '.jpg';

        $fileService = $this->createMock(FileService::class);
        $fileService
            ->expects(self::once())
            ->method('putPublicObject')
            ->with('/tmp/avatar.jpg', 'image/jpeg', $expectedKey)
            ->willReturn('https://cdn.example/' . $expectedKey);

        $service = new ProfilePictureService($fileService);

        $url = $service->uploadImage('/tmp/avatar.jpg', 'image/jpeg', $uuid);

        self::assertSame('https://cdn.example/' . $expectedKey, $url);
    }

    // ── MIME violations ───────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Creates a temp file whose content is repeated bytes (simulates an oversized payload).
     */
    private function makeLargeFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ppic_large_');
        $this->tmpFiles[] = $path;
        // Write just over 500 KB of zero bytes (not a valid image — size check fires first)
        file_put_contents($path, str_repeat("\x00", ProfilePictureService::MAX_BYTES + 1));

        return $path;
    }
}
