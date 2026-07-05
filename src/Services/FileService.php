<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\File;
use App\Services\Authentification\JWTService;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

class FileService
{
    protected ?S3Client $s3Client;

    public function __construct(
        protected CacheInterface $cache,
        protected JWTService   $jwtService,
        protected EntityManagerInterface $em,
        protected RequestStack $requestStack,
    )
    {
        // constructor body
        $mode = $_ENV['FILE_STORAGE_MODE'] ?? 'local';
        if ($mode === 'local') {
            // todo : implement local file storage
        } else {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'endpoint' => $_ENV['AWS_ENDPOINT'] ?? 'http://localhost:4566',
                'use_path_style_endpoint' => ($_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'true') === 'true',
                'credentials' => [
                    'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? 'none',
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? 'none',
                ],
                'http' => [
                    'connect_timeout' => 5,
                    'timeout' => 15,
                ],
            ]);
        }
    }

    /**
     * Use this function once you have authenticated the user
     * WARNING : this function does not check for authentication and authorization.
     *
     * @return string : JWT token
     */
    public function getUploadRequest(array $moreData = []): string
    {
        // get timestamp now + 10 minutes
        $expire = time() + 60 * 10;

        $payload = [
            'scope' => ['web/app/upload', 'web/api/upload'],
            'exp' => $expire,
            'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
            ...$moreData,
        ];

        return $this->jwtService->generateToken($payload);
    }

    public function storeFile(
        string $filepath,
        string $mime,
        File $file
    ): array
    {
        $data = $this->s3Client->putObject([
            'Bucket' => $_ENV['AWS_BUCKET'],
            'ContentType' => $mime,
            'Key' => $file->getKey() . '.' . $file->getExtension(),
            'Body' => fopen($filepath, 'r+'),
            'ACL' => 'private',
        ]);

        $file->setBucket($_ENV['AWS_BUCKET']);
        $file->setS3uuid($data['ETag'] ?? '');
        $file->setBucketUrl($_ENV['PUBLIC_URL_BUCKET']);
        $this->em->persist($file);
        $this->em->flush();

        return [
            'id' => $file->getId()?->toString(),
            'url' => $this->generateSignedUrl($file),
            'filename' => $file->getId() . '.' . $file->getExtension(),
            '@id' => '/api/files/' . $file->getId()?->toString(),
        ];
    }

    public function deleteFile(File $file): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $file->getBucket(),
            'Key' => $file->getKey() . '.' . $file->getExtension(),
        ]);
    }

    public function generateSignedUrl(File $file): string
    {
        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $file->getBucket(),
            'Key' => $file->getKey() . '.' . $file->getExtension(),
        ]);

        return (string)$this->s3Client->createPresignedRequest($cmd, '+24 hours')->getUri();
    }

    /**
     * Return the maximum upload file size in bytes allowed by the server.
     */
    public function getMaxUploadFileSize(): int
    {
        $size = ini_get('upload_max_filesize');
        $size = trim($size);
        $unit = strtolower($size[strlen($size) - 1]);
        $value = (int)$size;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public function test(): void
    {
        if (!$this->s3Client instanceof S3Client) {
            throw new Exception('S3 client not initialized');
        }

        $bucket = $_ENV['AWS_BUCKET'] ?? 'none';
        $key = 'Screenshot 2025-01-24 at 20.41.57.png';

        dd($this->s3Client->getObjectUrl($bucket, $key));
    }
}
