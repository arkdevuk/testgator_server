<?php

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\File;
use App\Services\FileService;
use Doctrine\ORM\EntityManagerInterface;

class FileStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FileService            $fileService,
        private readonly EntityManagerInterface $em,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof Delete && $data instanceof File) {
            $this->fileService->deleteFile($data);
            $this->em->remove($data);
            $this->em->flush();
        }

        return null;
    }
}
