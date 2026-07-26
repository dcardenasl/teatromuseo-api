<?php

declare(strict_types=1);

namespace App\Libraries\Storage\Drivers;

use App\Libraries\Storage\StorageDriverInterface;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

/**
 * AWS S3 Storage Driver
 *
 * Stores files on Amazon S3 using Flysystem
 */
class S3Driver implements StorageDriverInterface
{
    protected Filesystem $filesystem;
    protected string $bucket;
    protected string $region;
    protected ?string $baseUrl;

    public function __construct()
    {
        $this->bucket = (string) (getenv('AWS_BUCKET') ?: env('AWS_BUCKET', ''));
        $this->region = (string) (getenv('AWS_DEFAULT_REGION') ?: env('AWS_DEFAULT_REGION', 'us-east-1'));
        $baseUrl = (string) (getenv('AWS_URL') ?: env('AWS_URL', ''));
        $this->baseUrl = $baseUrl === '' ? null : $baseUrl;

        if (empty($this->bucket)) {
            throw new \RuntimeException('AWS_BUCKET environment variable is required for S3 driver');
        }

        $client = new S3Client([
            'credentials' => [
                'key' => (string) (getenv('AWS_ACCESS_KEY_ID') ?: env('AWS_ACCESS_KEY_ID', '')),
                'secret' => (string) (getenv('AWS_SECRET_ACCESS_KEY') ?: env('AWS_SECRET_ACCESS_KEY', '')),
            ],
            'region' => $this->region,
            'version' => 'latest',
        ]);

        $adapter = new AwsS3V3Adapter($client, $this->bucket);
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Store a file
     *
     * @param string $path Path where to store the file
     * @param mixed $contents File contents (string or resource)
     * @return bool Success status
     */
    public function store(string $path, $contents): bool
    {
        try {
            if (is_resource($contents)) {
                $this->filesystem->writeStream($path, $contents);
            } else {
                $this->filesystem->write($path, $contents);
            }
            return true;
        } catch (\Exception $e) {
            log_message('error', 'S3 storage error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve a file
     *
     * @param string $path Path to the file
     * @return string|false File contents or false on failure
     */
    public function retrieve(string $path): string|false
    {
        try {
            return $this->filesystem->read($path);
        } catch (\Exception $e) {
            log_message('error', 'S3 retrieval error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file
     *
     * @param string $path Path to the file
     * @return bool Success status
     */
    public function delete(string $path): bool
    {
        try {
            $this->filesystem->delete($path);
            return true;
        } catch (\Exception $e) {
            log_message('error', 'S3 deletion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @return bool True if exists, false otherwise
     */
    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file URL
     *
     * @param string $path Path to the file
     * @return string URL to access the file
     */
    public function url(string $path): string
    {
        if ($this->baseUrl) {
            return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        }

        // Default S3 URL format
        return sprintf(
            'https://%s.s3.%s.amazonaws.com/%s',
            $this->bucket,
            $this->region,
            ltrim($path, '/')
        );
    }

    /**
     * Get file size
     *
     * @param string $path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public function size(string $path): int|false
    {
        try {
            return $this->filesystem->fileSize($path);
        } catch (\Exception $e) {
            return false;
        }
    }
}
