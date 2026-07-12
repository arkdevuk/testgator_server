<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Services\FileService;
use App\Services\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FileController extends AbstractController
{
    public function __construct(private readonly FileService $fileService, private readonly SettingsService $settingsService)
    {
    }

    #[Route('/public/apx/upload', name: 'upload_file')]
    public function upload_file(
        Request $request,
    ): JsonResponse
    {
        // CORS (including OPTIONS preflight) is handled globally by NelmioCORSBundle.

        // Check whether uploads are enabled (setting: general.allow_upload, default: true)
        if ($this->settingsService->getSettingValue('allow_upload', 'general', 'true') === 'false') {
            return $this->json(['error' => 'Uploads are disabled', 'message' => 'uploads_disabled'], 403);
        }

        // max upload size in bytes (FILE_MAX_SIZE_MB, default 10 MB)
        $maxSize = (int)($_ENV['FILE_MAX_SIZE_MB'] ?? '10') * 1024 * 1024;
        // valid formats : jpg, jpeg, png, gif, pdf
        $allowedExt = ['jpeg', 'jpg', 'png', 'gif', 'pdf', 'txt', 'mov', 'mp4', 'avi', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
        $allowedMime = [
            'media' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
            'document' => ['application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
            'video' => ['video/quicktime', 'video/mp4', 'video/x-msvideo'],
        ];

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            // A body larger than PHP's post_max_size arrives with an empty $_FILES,
            // so an oversized POST looks like "no file". Since the pre-flight size
            // check (/api/uploads/request) has been removed, surface the same
            // "file too big" error here instead of a misleading "no file".
            if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxSize) {
                return $this->json([
                    'error' => 'File too big',
                    'message' => 'file_too_big',
                ], 400);
            }

            return $this->json([
                'error' => 'No file uploaded',
                'message' => 'no_file_uploaded',
            ], 400);
        }

        // Enforce the size limit from the actual uploaded file, not the
        // client-supplied Content-Length header (which can be spoofed).
        if ($file->getSize() > $maxSize) {
            return $this->json([
                'error' => 'File too big',
                'message' => 'file_too_big',
            ], 400);
        }

        $fileType = $mime = $file->getMimeType();
        $fileExt = $file->getClientOriginalExtension();
        $fileExt = strtolower($fileExt);

        // check ext
        if (!in_array($fileExt, $allowedExt, true)) {
            $data['more'] = [$fileExt, $allowedExt];
            $data['error'] = 'File format not allowed';
            $data['message'] = 'file_format_not_allowed';

            return $this->json($data, 400);
        }
        $type = array_find_key($allowedMime, fn($v): bool => in_array($fileType, $v, true));

        if ($type === null) {
            $data['error'] = 'File type not allowed';
            $data['message'] = 'file_type_not_allowed';

            return $this->json($data, 400);
        }

        $fileEntity = new File($file->getBasename());
        $fileEntity->setExtension($fileExt);

        // Optional "public" flag sent alongside the file (form field). Defaults to false.
        $fileEntity->setPublic($request->request->getBoolean('public'));

        // Record the authenticated user who uploaded the file.
        $currentUser = $this->getUser();
        if ($currentUser instanceof User) {
            $fileEntity->setUploadedBy($currentUser);
        }

        // get temps path using php native
        $newPath = tempnam(sys_get_temp_dir(), 'upload');
        $file->move(dirname($newPath), basename($newPath));

        $data = $this->fileService->storeFile($newPath, $mime, $fileEntity);
        @unlink($newPath);

        return $this->json($data);
    }
}
