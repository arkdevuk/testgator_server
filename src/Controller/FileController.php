<?php

namespace App\Controller;

use App\Entity\File;
use App\Services\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FileController extends AbstractController
{
    #[Route('/public/apx/upload', name: 'upload_file')]
    public function upload_file(
        FileService $fileService,
        Request     $request,
    ): Response
    {
        // CORS
        header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Allow: GET, POST, OPTIONS, PUT, DELETE");
        if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
            die();
        }
        /** @noinspection ObGetCleanCanBeUsedInspection */
        ob_get_contents();
        ob_end_clean();

        // max size in Mb : 9Mb
        $maxSize = (int)($_ENV['FILE_MAX_SIZE_MB'] ?? '10') * 1024 * 1024;
        // valid formats : jpg, jpeg, png, gif, pdf
        $allowedExt = ['jpeg', 'jpg', 'png', 'gif', 'pdf', 'txt'];
        $allowedMime = [
            'media' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
            'document' => ['application/pdf', 'text/plain'],
        ];

        $size = (int)$_SERVER['CONTENT_LENGTH'];

        if ($size > $maxSize) {
            return $this->json([
                'error' => 'File too big',
                'message' => 'file_too_big',
            ], 400);
        }

        /**
         * @var $file UploadedFile
         */
        $file = $request->files->get('file');

        if (empty($file)) {
            return $this->json([
                'error' => 'No file uploaded',
                'message' => 'no_file_uploaded',
            ], 400);
        }

        //dd($file,$file->getMimeType());die;

        $fileType = $mime = $file->getMimeType();
        $fileExt = $file->getClientOriginalExtension();
        $fileExt = strtolower($fileExt);

        // check ext
        if (!in_array($fileExt, $allowedExt)) {
            $data['more'] = [$fileExt, $allowedExt];
            $data['error'] = 'File format not allowed';
            $data['message'] = 'file_format_not_allowed';
            return $this->json($data, 400);
        }

        // detect type using mime
        $type = null;
        foreach ($allowedMime as $k => $v) {
            if (in_array($fileType, $v, true)) {
                $type = $k;
                break;
            }
        }

        if ($type === null) {
            $data['error'] = 'File type not allowed';
            $data['message'] = 'file_type_not_allowed';
            return $this->json($data, 400);
        }

        $fileEntity = new File($file->getBasename());
        $fileEntity->setExtension($fileExt);

        // get temps path using php native
        $newPath = tempnam(sys_get_temp_dir(), 'upload');
        $file->move(dirname($newPath), basename($newPath));

        $data = $fileService->storeFile($newPath, $mime, $fileEntity);
        @unlink($newPath);

        return $this->json($data);
    }
}
