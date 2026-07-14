<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Services\FileService;
use App\State\FileStateProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Unit tests for the GET /api/files/{id} access rules.
 *
 *  - dev team (User of type USER) can read any file;
 *  - testers can read public files and files they uploaded;
 *  - anything else is denied.
 */
class FileStateProviderTest extends TestCase
{
    public function testReturnsNullWhenFileNotFound(): void
    {
        $provider = $this->makeProvider(null, $this->makeUser(isTester: false));

        self::assertNull($provider->provide(new Get(), ['id' => 'missing']));
    }

    public function testDevCanReadAnyPrivateFile(): void
    {
        $file = $this->makeFile(public: false, uploader: $this->makeUser(isTester: true));
        $provider = $this->makeProvider($file, $this->makeUser(isTester: false));

        self::assertSame($file, $provider->provide(new Get(), ['id' => 'x']));
    }

    public function testTesterCanReadPublicFile(): void
    {
        $file = $this->makeFile(public: true, uploader: null);
        $provider = $this->makeProvider($file, $this->makeUser(isTester: true));

        self::assertSame($file, $provider->provide(new Get(), ['id' => 'x']));
    }

    public function testTesterCanReadOwnPrivateFile(): void
    {
        $tester = $this->makeUser(isTester: true);
        $file = $this->makeFile(public: false, uploader: $tester);
        $provider = $this->makeProvider($file, $tester);

        self::assertSame($file, $provider->provide(new Get(), ['id' => 'x']));
    }

    public function testTesterCannotReadOthersPrivateFile(): void
    {
        $file = $this->makeFile(public: false, uploader: $this->makeUser(isTester: true));
        $provider = $this->makeProvider($file, $this->makeUser(isTester: true));

        $this->expectException(AccessDeniedException::class);
        $provider->provide(new Get(), ['id' => 'x']);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function makeProvider(?File $file, ?User $currentUser): FileStateProvider
    {
        $repo = $this->createStub(FileRepository::class);
        $repo->method('find')->willReturn($file);

        $fileService = $this->createStub(FileService::class);
        $fileService->method('generateUrl')->willReturn('https://example.test/file');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($currentUser);

        return new FileStateProvider($repo, $fileService, $security);
    }

    private function makeUser(bool $isTester): User
    {
        $user = $this->createStub(User::class);
        $user->method('isTester')->willReturn($isTester);
        // A distinct id per user object so ownership comparisons are meaningful.
        $user->method('getId')->willReturn(Uuid::v7());

        return $user;
    }

    private function makeFile(bool $public, ?User $uploader): File
    {
        $file = $this->createStub(File::class);
        $file->method('isPublic')->willReturn($public);
        $file->method('getUploadedBy')->willReturn($uploader);

        return $file;
    }
}
