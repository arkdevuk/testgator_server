<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProjectImageService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $em,
        private readonly ProfilePictureService $profilePictureService,
    ) {
    }

    public function findProject(int $id): ?Project
    {
        return $this->projectRepository->find($id);
    }

    /**
     * Validate, upload, and persist the project picture URL.
     *
     * @return string The new picture URL
     *
     * @throws InvalidArgumentException on image validation failure
     * @throws RuntimeException         on upload failure
     */
    public function setProjectPicture(Project $project, UploadedFile $file): string
    {
        $mime = $this->profilePictureService->validateImage($file->getPathname());

        $allMimes = array_merge(ProfilePictureService::ALLOWED_MIMES, ProfilePictureService::BANNER_ALLOWED_MIMES);
        $ext = $allMimes[$mime];
        $key = 'project-pictures/'.$project->getId().'.'.$ext;
        $url = $this->profilePictureService->uploadPublicImage($file->getPathname(), $mime, $key);

        $project->setProjectPictureUrl($url);
        $this->em->flush();

        return $url;
    }

    /**
     * Reset the project picture to null.
     */
    public function deleteProjectPicture(Project $project): void
    {
        $project->setProjectPictureUrl(null);
        $this->em->flush();
    }

    /**
     * Validate, upload, and persist the project banner URL.
     *
     * @return string The new banner URL
     *
     * @throws InvalidArgumentException on image validation failure
     * @throws RuntimeException         on upload failure
     */
    public function setProjectBanner(Project $project, UploadedFile $file): string
    {
        $mime = $this->profilePictureService->validateBannerImage($file->getPathname());

        $key = 'project-banners/'.$project->getId().'.png';
        $url = $this->profilePictureService->uploadPublicImage($file->getPathname(), $mime, $key);

        $project->setProjectBannerUrl($url);
        $this->em->flush();

        return $url;
    }

    /**
     * Reset the project banner to null.
     */
    public function deleteProjectBanner(Project $project): void
    {
        $project->setProjectBannerUrl(null);
        $this->em->flush();
    }
}
