<?php

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\File;
use App\Repository\FileRepository;
use App\Services\FileService;

class FileStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly FileService    $fileService,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $file = $this->fileRepository->find($uriVariables['id']);
        if (!$file instanceof File) {
            return null;
        }

        if ($operation instanceof Get) {
            $file->setSignedUrl($this->fileService->generateSignedUrl($file));
        }

        return $file;
    }
}
