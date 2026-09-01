<?php

declare(strict_types=1);

namespace App\Libraries\Storage\Drivers;

use App\Libraries\Storage\StorageDriverInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Local File Storage Driver
 *
 * Stores files on the local filesystem using Flysystem
 */
class LocalDriver implements StorageDriverInterface
{
    protected Filesystem $filesystem;
    protected string $basePath;

    public function __construct()
    {
        $uploadPath = config('Api')->fileUploadPath;

        // Ensure path is absolute (relative to project root, not public)
        if (!str_starts_with($uploadPath, '/')) {
            // Go up from public/ to project root, then add the upload path
            $projectRoot = dirname(FCPATH);
            $this->basePath = rtrim($projectRoot, '/') . '/' . ltrim($uploadPath, '/');
        } else {
            $this->basePath = $uploadPath;
        }

        // Create directory if it doesn't exist
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }

        // Ensure directory is writable
        if (!is_writable($this->basePath)) {
            @chmod($this->basePath, 0775);
        }

        $visibilityConverter = new \League\Flysystem\UnixVisibility\PortableVisibilityConverter(
            0644,
            0600,
            0755,
            0700,
            \League\Flysystem\Visibility::PUBLIC
        );
        $adapter = new LocalFilesystemAdapter($this->basePath, $visibilityConverter);
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
            log_message('error', 'Local storage error: ' . $e->getMessage());
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
            log_message('error', 'Local retrieval error: ' . $e->getMessage());
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
            log_message('error', 'Local deletion error: ' . $e->getMessage());
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
        // Files are accessible via the public/ directory symlink
        return base_url('uploads/' . $path);
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
