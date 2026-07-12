<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Services\FileService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class FileStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly FileService $fileService,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $file = $this->fileRepository->find($uriVariables['id']);
        if (!$file instanceof File) {
            return null;
        }

        if ($operation instanceof Get) {
            $this->denyUnlessViewable($file);
            $file->setSignedUrl($this->fileService->generateUrl($file));
        }

        return $file;
    }

    /**
     * Access rules for reading a file:
     *  - dev team (User of type USER) can access every file;
     *  - testers can only access public files and files they uploaded.
     */
    private function denyUnlessViewable(File $file): void
    {
        $user = $this->security->getUser();

        // Dev team members can access all files.
        if ($user instanceof User && !$user->isTester()) {
            return;
        }

        // Public files are accessible to everyone.
        if ($file->isPublic()) {
            return;
        }

        // Testers may only access their own uploads.
        $uploader = $file->getUploadedBy();
        if ($user instanceof User
            && $uploader instanceof User
            && (string) $uploader->getId() === (string) $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You are not allowed to access this file.');
    }
}
