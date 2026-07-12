<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Services\ProjectImageService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles project picture and banner upload/delete.
 *
 * All four endpoints require ROLE_USER (dev team only).
 *
 * POST /api/projects/{id}/project-picture  — multipart, same rules as user profile picture
 *   (PNG/JPEG, ≤ 500 KB, ≤ 800 × 800 px, square)
 *
 * DELETE /api/projects/{id}/project-picture  — resets to null
 *
 * POST /api/projects/{id}/project-banner  — multipart PNG, ≤ 1024 × 1024 px, < 1 MB
 *
 * DELETE /api/projects/{id}/project-banner  — resets to null
 */
#[IsGranted('ROLE_USER')]
final class ProjectImageController extends AbstractController
{
    public function __construct(
        private readonly ProjectImageService $projectImageService,
    ) {
    }

    // ── POST /api/projects/{id}/project-picture ───────────────────────────────

    #[Route('/api/projects/{id}/project-picture', name: 'project_upload_picture', methods: ['POST'])]
    public function uploadProjectPicture(int $id, Request $request): JsonResponse
    {
        $project = $this->projectImageService->findProject($id);
        if (!$project instanceof Project) {
            return $this->json(['error' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            return $this->json(['error' => '`file` is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $url = $this->projectImageService->setProjectPicture($project, $file);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['projectPictureUrl' => $url]);
    }

    // ── DELETE /api/projects/{id}/project-picture ─────────────────────────────

    #[Route('/api/projects/{id}/project-picture', name: 'project_delete_picture', methods: ['DELETE'])]
    public function deleteProjectPicture(int $id): JsonResponse
    {
        $project = $this->projectImageService->findProject($id);
        if (!$project instanceof Project) {
            return $this->json(['error' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->projectImageService->deleteProjectPicture($project);

        return $this->json(['projectPictureUrl' => null]);
    }

    // ── POST /api/projects/{id}/project-banner ────────────────────────────────

    #[Route('/api/projects/{id}/project-banner', name: 'project_upload_banner', methods: ['POST'])]
    public function uploadProjectBanner(int $id, Request $request): JsonResponse
    {
        $project = $this->projectImageService->findProject($id);
        if (!$project instanceof Project) {
            return $this->json(['error' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            return $this->json(['error' => '`file` is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $url = $this->projectImageService->setProjectBanner($project, $file);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['projectBannerUrl' => $url]);
    }

    // ── DELETE /api/projects/{id}/project-banner ──────────────────────────────

    #[Route('/api/projects/{id}/project-banner', name: 'project_delete_banner', methods: ['DELETE'])]
    public function deleteProjectBanner(int $id): JsonResponse
    {
        $project = $this->projectImageService->findProject($id);
        if (!$project instanceof Project) {
            return $this->json(['error' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->projectImageService->deleteProjectBanner($project);

        return $this->json(['projectBannerUrl' => null]);
    }
}
