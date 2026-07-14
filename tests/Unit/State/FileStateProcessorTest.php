<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use App\Entity\File;
use App\Entity\User;
use App\Services\FileService;
use App\State\FileStateProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Unit tests for the DELETE /api/files/{id} access rules.
 *
 *  - dev-team admins (User of type USER holding ROLE_ADMIN) can delete any file;
 *  - everyone else (testers and non-admin dev users) can only delete their own uploads.
 */
class FileStateProcessorTest extends TestCase
{
    public function testAdminCanDeleteAnyFile(): void
    {
        $file = $this->makeFile($this->makeUser(isTester: false)); // uploaded by someone else
        $admin = $this->makeUser(isTester: false);

        $this->process($file, $admin, isAdmin: true, expectDeleted: true);
    }

    public function testDevCanDeleteOwnFile(): void
    {
        $user = $this->makeUser(isTester: false);
        $file = $this->makeFile($user);

        $this->process($file, $user, isAdmin: false, expectDeleted: true);
    }

    public function testTesterCanDeleteOwnFile(): void
    {
        $tester = $this->makeUser(isTester: true);
        $file = $this->makeFile($tester);

        $this->process($file, $tester, isAdmin: false, expectDeleted: true);
    }

    public function testNonAdminDevCannotDeleteOthersFile(): void
    {
        $file = $this->makeFile($this->makeUser(isTester: false));
        $intruder = $this->makeUser(isTester: false);

        $this->expectException(AccessDeniedException::class);
        $this->process($file, $intruder, isAdmin: false, expectDeleted: false);
    }

    public function testTesterCannotDeleteOthersFile(): void
    {
        $file = $this->makeFile($this->makeUser(isTester: true));
        $intruder = $this->makeUser(isTester: true);

        $this->expectException(AccessDeniedException::class);
        $this->process($file, $intruder, isAdmin: false, expectDeleted: false);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * Runs the Delete operation and asserts whether the file was actually removed.
     */
    private function process(File $file, User $currentUser, bool $isAdmin, bool $expectDeleted): void
    {
        $fileService = $this->createMock(FileService::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $fileService->expects($expectDeleted ? self::once() : self::never())
            ->method('deleteFile')->with($file);
        $em->expects($expectDeleted ? self::once() : self::never())
            ->method('remove')->with($file);
        $em->expects($expectDeleted ? self::once() : self::never())
            ->method('flush');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($currentUser);
        $security->method('isGranted')->willReturn($isAdmin);

        $processor = new FileStateProcessor($fileService, $em, $security);
        $processor->process($file, new Delete(), ['id' => 'x']);
    }

    private function makeUser(bool $isTester): User
    {
        $user = $this->createStub(User::class);
        $user->method('isTester')->willReturn($isTester);
        $user->method('getId')->willReturn(Uuid::v7());

        return $user;
    }

    private function makeFile(?User $uploader): File
    {
        $file = $this->createStub(File::class);
        $file->method('getUploadedBy')->willReturn($uploader);

        return $file;
    }
}
