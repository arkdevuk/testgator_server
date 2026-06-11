<?php

namespace App\Controller;

use App\Entity\User;
use App\Services\Authentification\GuestAuthService;
use App\Services\Entities\TestPlanManager;
use App\Services\FileService;
use App\Traits\GuidAware;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthUploadController extends AbstractController
{
    use GuidAware;

    #[Route('/api/uploads/request', name: 'upload_request')]
    public function upload_request(
        FileService $fileService,
        Request     $request,
    ): Response
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            return $this->json([
                'error' => 'Unauthorized',
            ], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Invalid JSON',
            ], 400);
        }

        // check if filename and filesize are provided in json body
        $filename = $payload['filename'] ?? null;
        $filesize = $payload['size'] ?? null;
        if ($filename === null || $filesize === null) {
            return $this->json([
                'error' => 'Missing filename or filesize',
            ], 400);
        }
        // get upload max size from current php.ini configuration
        $maxSize = $fileService->getMaxUploadFileSize();
        // check if the file size is within the limits
        if ($filesize > $maxSize) {
            return $this->json([
                'error' => 'File size exceeds the limit',
            ], 400);
        }
        $jwtPayload = [
            'user' => [
                'type' => $u->isTester() ? 'tester' : 'user',
                'id' => $u->getId()?->toString(),
                'roles' => $u->getRoles(),
            ],
            'file' => [
                'name' => $filename,
                'size' => $filesize,
            ],
            'requestID' => time() . $this->generateHumanHash(54),
        ];
        // auth already handled, generate a JWT token
        return $this->json([
            'jwt' => $fileService->getUploadRequest($jwtPayload),
            'max_size' => $maxSize,
        ]);
    }
}
