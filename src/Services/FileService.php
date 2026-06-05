<?php

namespace App\Services;

use App\Entity\File;
use App\Entity\Media;
use App\Services\Authentification\JWTService;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

class FileService
{
    /**
     * @var S3Client|null
     */
    protected ?S3Client $s3Client;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * @var JWTService
     */
    protected JWTService $jwtService;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $em;

    protected RequestStack $requestStack;

    public function __construct(
        CacheInterface         $uploadRequestCache,
        JWTService             $jwtService,
        EntityManagerInterface $em,
        RequestStack $requestStack,
    )
    {
        $this->cache = $uploadRequestCache;
        $this->jwtService = $jwtService;
        $this->em = $em;
        $this->requestStack = $requestStack;
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
                ]
            ]);
        }
    }

    /**
     * Use this function once you have authenticated the user
     * WARNING : this function does not check for authentication and authorization
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
        File   $file
    ): array
    {
        $data = $this->s3Client->putObject([
            'Bucket' => $_ENV['AWS_BUCKET'],
            'ContentType' => $mime,
            'Key' => $file->getKey() . '.' . $file->getExtension(),
            'Body' => fopen($filepath, 'r+'),
            'ACL' => 'public-read',
        ]);

        $file->setBucket($_ENV['AWS_BUCKET']);
        $file->setS3uuid($data['ETag'] ?? '');
        $file->setBucketUrl($_ENV['PUBLIC_URL_BUCKET']);
        $this->em->persist($file);
        $this->em->flush();
        return [
            'id' => $file->getId()?->toString(),
            'url' => $file->getUrl(),
            'filename' => $file->getId() . '.' . $file->getExtension(),
            '@id' => '/api/files/' . $file->getId()?->toString(),
        ];
    }

    /**
     * Return the maximum upload file size in bytes allowed by the server
     * @return int
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

    public function test()
    {
        if ($this->s3Client === null) {
            throw new \Exception('S3 client not initialized');
        }

        $bucket = $_ENV['AWS_BUCKET'] ?? 'none';
        $key = 'Screenshot 2025-01-24 at 20.41.57.png';

        dd($this->s3Client->getObjectUrl($bucket, $key));

    }
}
