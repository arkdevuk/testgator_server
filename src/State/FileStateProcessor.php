<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\File;
use App\Entity\User;
use App\Services\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class FileStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof Delete && $data instanceof File) {
            $this->denyUnlessDeletable($data);
            $this->fileService->deleteFile($data);
            $this->em->remove($data);
            $this->em->flush();
        }

        return null;
    }

    /**
     * Deletion rules:
     *  - dev-team admins (User of type USER holding ROLE_ADMIN) can delete any file;
     *  - everyone else (testers and non-admin dev users) can only delete files they uploaded.
     */
    private function denyUnlessDeletable(File $file): void
    {
        $user = $this->security->getUser();

        // Dev-team admins can delete every file.
        if ($user instanceof User && !$user->isTester() && $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Everyone else may only delete their own uploads.
        $uploader = $file->getUploadedBy();
        if ($user instanceof User
            && $uploader instanceof User
            && (string)$uploader->getId() === (string)$user->getId()) {
            return;
        }

        throw new AccessDeniedException('You are not allowed to delete this file.');
    }
}
