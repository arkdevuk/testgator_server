<?php

declare(strict_types=1);

namespace App\Services;

use Aws\S3\S3Client;
use InvalidArgumentException;
use RuntimeException;

/**
 * Validates and stores project/user images in the public S3 bucket.
 *
 * User profile picture rules (validateImage):
 *   - ≤ 500 KB, ≤ 800 × 800 px, must be square, PNG or JPEG
 *
 * Project banner rules (validateBannerImage):
 *   - < 1 MB, ≤ 1024 × 1024 px, PNG only, non-square allowed
 *
 * All uploads go to AWS_PUBLIC_BUCKET with no ACL header (bucket is public by policy).
 */
class ProfilePictureService
{
    public const MAX_BYTES = 500 * 1024;         // 500 KB  — profile picture
    public const MAX_PX = 800;                    // profile picture max dimension
    public const ALLOWED_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    public const BANNER_MAX_BYTES = 1024 * 1024; // 1 MB   — project banner
    public const BANNER_MAX_PX = 1024;            // project banner max dimension
    public const BANNER_ALLOWED_MIMES = ['image/png' => 'png'];

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
     *
     * @throws InvalidArgumentException with a user-facing message on any violation
     */
    public function validateImage(string $tmpPath): string
    {
        if (!is_readable($tmpPath)) {
            throw new InvalidArgumentException('Uploaded file is not readable.');
        }

        if (filesize($tmpPath) > self::MAX_BYTES) {
            throw new InvalidArgumentException(sprintf('Image must be less than %d KB.', self::MAX_BYTES / 1024));
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new InvalidArgumentException('File is not a valid image.');
        }

        $mime = $info['mime'];
        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new InvalidArgumentException('Only PNG and JPEG images are accepted.');
        }

        [$width, $height] = $info;

        if ($width > self::MAX_PX || $height > self::MAX_PX) {
            throw new InvalidArgumentException(sprintf('Image must not exceed %d×%d px (uploaded image is %d×%d).', self::MAX_PX, self::MAX_PX, $width, $height));
        }

        if ($width !== $height) {
            throw new InvalidArgumentException(sprintf('Image must be square — uploaded image is %d×%d px.', $width, $height));
        }

        return $mime;
    }

    /**
     * Validates a project banner image.
     *
     * Rules: PNG only, < 1 MB, ≤ 1024 × 1024 px (non-square allowed).
     *
     * @return string Always 'image/png'
     *
     * @throws InvalidArgumentException on any violation
     */
    public function validateBannerImage(string $tmpPath): string
    {
        if (!is_readable($tmpPath)) {
            throw new InvalidArgumentException('Uploaded file is not readable.');
        }

        if (filesize($tmpPath) >= self::BANNER_MAX_BYTES) {
            throw new InvalidArgumentException(sprintf('Banner must be less than %d MB.', self::BANNER_MAX_BYTES / (1024 * 1024)));
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new InvalidArgumentException('File is not a valid image.');
        }

        $mime = $info['mime'];
        if (!array_key_exists($mime, self::BANNER_ALLOWED_MIMES)) {
            throw new InvalidArgumentException('Only PNG images are accepted for banners.');
        }

        [$width, $height] = $info;

        if ($width > self::BANNER_MAX_PX || $height > self::BANNER_MAX_PX) {
            throw new InvalidArgumentException(sprintf('Banner must not exceed %d px on either side (uploaded image is %d×%d).', self::BANNER_MAX_PX, $width, $height));
        }

        return $mime;
    }

    /**
     * Uploads the validated image to the public S3 bucket and returns its URL.
     *
     * Uses AWS_PUBLIC_BUCKET / PUBLIC_URL_PUBLIC_BUCKET — the bucket is public
     * by policy so no ACL header is sent.
     *
     * @param string $tmpPath Absolute path to the temporary file
     * @param string $mime MIME type returned by validateImage() / validateBannerImage()
     * @param string $key Full S3 object key (e.g. 'profile-pictures/uuid.png')
     *
     * @return string Public URL of the stored image
     *
     * @throws RuntimeException when S3 is not configured
     */
    public function uploadPublicImage(string $tmpPath, string $mime, string $key): string
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

        return rtrim($_ENV['PUBLIC_URL_PUBLIC_BUCKET'] ?? '', '/') . '/' . $key;
    }

    /**
     * Uploads the validated image to the public S3 bucket and returns its URL.
     *
     * Uses AWS_PUBLIC_BUCKET / PUBLIC_URL_PUBLIC_BUCKET — the bucket is public
     * by policy so no ACL header is sent.
     *
     * @param string $tmpPath Absolute path to the temporary file
     * @param string $mime MIME type returned by validateImage()
     * @param string $uuid User UUID — used as the S3 object key
     *
     * @return string Public URL of the stored image
     *
     * @throws RuntimeException when S3 is not configured
     */
    public function uploadImage(string $tmpPath, string $mime, string $uuid): string
    {
        $allMimes = array_merge(self::ALLOWED_MIMES, self::BANNER_ALLOWED_MIMES);
        $ext = $allMimes[$mime];
        $key = 'profile-pictures/' . $uuid . '.' . $ext;

        return $this->uploadPublicImage($tmpPath, $mime, $key);
    }
}
