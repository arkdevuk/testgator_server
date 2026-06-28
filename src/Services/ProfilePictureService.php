<?php

namespace App\Services;

use Aws\S3\S3Client;

/**
 * Validates and stores user profile pictures.
 *
 * Validation rules (enforced by validateImage()):
 *   - Maximum 500 KB
 *   - Maximum 800 × 800 px
 *   - Must be square (width === height)
 *   - MIME must be image/png or image/jpeg
 *
 * uploadImage() stores the file in S3 with ACL: public-read and returns the
 * public URL. Profile pictures are intentionally public (unlike answer files).
 */
class ProfilePictureService
{
    public const MAX_BYTES = 500 * 1024; // 500 KB
    public const MAX_PX = 800;
    public const ALLOWED_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private ?S3Client $s3Client = null;

    public function __construct()
    {
        if (($_ENV['FILE_STORAGE_MODE'] ?? 's3') !== 'local') {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'endpoint' => $_ENV['AWS_ENDPOINT'] ?? 'http://localhost:4566',
                'use_path_style_endpoint' => ($_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'true') === 'true',
                'credentials' => [
                    'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? 'none',
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? 'none',
                ],
                'http' => ['connect_timeout' => 5, 'timeout' => 15],
            ]);
        }
    }

    /**
     * Validates the image at $tmpPath.
     *
     * @return string Detected MIME type (image/png or image/jpeg)
     * @throws \InvalidArgumentException with a user-facing message on any violation
     */
    public function validateImage(string $tmpPath): string
    {
        if (!is_readable($tmpPath)) {
            throw new \InvalidArgumentException('Uploaded file is not readable.');
        }

        if (filesize($tmpPath) > self::MAX_BYTES) {
            throw new \InvalidArgumentException(
                sprintf('Image must be less than %d KB.', self::MAX_BYTES / 1024)
            );
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new \InvalidArgumentException('File is not a valid image.');
        }

        $mime = $info['mime'] ?? '';
        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new \InvalidArgumentException('Only PNG and JPEG images are accepted.');
        }

        [$width, $height] = $info;

        if ($width > self::MAX_PX || $height > self::MAX_PX) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Image must not exceed %d×%d px (uploaded image is %d×%d).',
                    self::MAX_PX, self::MAX_PX,
                    $width, $height,
                )
            );
        }

        if ($width !== $height) {
            throw new \InvalidArgumentException(
                sprintf('Image must be square — uploaded image is %d×%d px.', $width, $height)
            );
        }

        return $mime;
    }

    /**
     * Uploads the validated image to S3 with public-read ACL.
     *
     * @param string $tmpPath Absolute path to the temporary file
     * @param string $mime MIME type returned by validateImage()
     * @param string $uuid User UUID — used as the S3 object key
     * @return string           Public URL of the stored image
     * @throws \RuntimeException when S3 is not configured
     */
    public function uploadImage(string $tmpPath, string $mime, string $uuid): string
    {
        if ($this->s3Client === null) {
            throw new \RuntimeException('S3 storage is not configured (FILE_STORAGE_MODE=local).');
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $key = 'profile-pictures/' . $uuid . '.' . $ext;

        $this->s3Client->putObject([
            'Bucket' => $_ENV['AWS_BUCKET'],
            'Key' => $key,
            'Body' => fopen($tmpPath, 'rb'),
            'ContentType' => $mime,
            'ACL' => 'public-read',
        ]);

        return rtrim($_ENV['PUBLIC_URL_BUCKET'] ?? '', '/') . '/' . $key;
    }
}
