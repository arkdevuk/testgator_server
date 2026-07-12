<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\File;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;

class FileService
{
    protected ?S3Client $s3Client = null;

    public function __construct(
        protected EntityManagerInterface $em,
    ) {
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

    public function storeFile(
        string $filepath,
        string $mime,
        File $file
    ): array {
        // Public files go to the public bucket (public by policy, so no ACL header
        // is sent — same convention as ProfilePictureService); everything else
        // stays in the private bucket and is only reachable through a signed URL.
        $isPublic = $file->isPublic();
        $bucket = $isPublic ? $_ENV['AWS_PUBLIC_BUCKET'] : $_ENV['AWS_BUCKET'];
        $bucketUrl = $isPublic ? $_ENV['PUBLIC_URL_PUBLIC_BUCKET'] : $_ENV['PUBLIC_URL_BUCKET'];

        $params = [
            'Bucket' => $bucket,
            'ContentType' => $mime,
            'Key' => $file->getKey().'.'.$file->getExtension(),
            'Body' => fopen($filepath, 'r+'),
        ];

        if (!$isPublic) {
            $params['ACL'] = 'private';
        }

        $data = $this->s3Client->putObject($params);

        $file->setBucket($bucket);
        $file->setS3uuid($data['ETag'] ?? '');
        $file->setBucketUrl($bucketUrl);
        $this->em->persist($file);
        $this->em->flush();

        return [
            'id' => $file->getId()?->toString(),
            'url' => $this->generateUrl($file),
            'filename' => $file->getId().'.'.$file->getExtension(),
            '@id' => '/api/files/'.$file->getId()?->toString(),
        ];
    }

    /**
     * Store a file in the public bucket under an explicit key and return its
     * public URL. The bucket is public by policy, so no ACL header is sent.
     *
     * Used for deterministic-key public assets (avatars, project images/banners)
     * that overwrite in place and are not tracked as File entities.
     *
     * @throws RuntimeException when S3 is not configured (FILE_STORAGE_MODE=local)
     */
    public function putPublicObject(string $tmpPath, string $mime, string $key): string
    {
        if (!$this->s3Client instanceof S3Client) {
            throw new RuntimeException('S3 storage is not configured (FILE_STORAGE_MODE=local).');
        }

        $this->s3Client->putObject([
            'Bucket' => $_ENV['AWS_PUBLIC_BUCKET'],
            'Key' => $key,
            'Body' => fopen($tmpPath, 'r'),
            'ContentType' => $mime,
        ]);

        return rtrim($_ENV['PUBLIC_URL_PUBLIC_BUCKET'] ?? '', '/').'/'.$key;
    }

    public function deleteFile(File $file): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $file->getBucket(),
            'Key' => $file->getKey().'.'.$file->getExtension(),
        ]);
    }

    /**
     * Public files are served directly from the public bucket URL; private
     * files get a short-lived signed URL.
     */
    public function generateUrl(File $file): string
    {
        if ($file->isPublic()) {
            return rtrim((string) $file->getBucketUrl(), '/')
                .'/'.$file->getKey().'.'.$file->getExtension();
        }

        return $this->generateSignedUrl($file);
    }

    public function generateSignedUrl(File $file): string
    {
        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $file->getBucket(),
            'Key' => $file->getKey().'.'.$file->getExtension(),
        ]);

        return (string) $this->s3Client->createPresignedRequest($cmd, '+24 hours')->getUri();
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
