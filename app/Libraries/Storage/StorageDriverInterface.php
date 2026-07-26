<?php

declare(strict_types=1);

namespace App\Libraries\Storage;

/**
 * Storage Driver Interface
 *
 * Defines the contract for file storage drivers (local, S3, etc.)
 */
interface StorageDriverInterface
{
    /**
     * Store a file
     *
     * @param string $path Path where to store the file
     * @param mixed $contents File contents (string or resource)
     * @return bool Success status
     */
    public function store(string $path, $contents): bool;

    /**
     * Retrieve a file
     *
     * @param string $path Path to the file
     * @return string|false File contents or false on failure
     */
    public function retrieve(string $path): string|false;

    /**
     * Delete a file
     *
     * @param string $path Path to the file
     * @return bool Success status
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @return bool True if exists, false otherwise
     */
    public function exists(string $path): bool;

    /**
     * Get file URL
     *
     * @param string $path Path to the file
     * @return string URL to access the file
     */
    public function url(string $path): string;

    /**
     * Get file size
     *
     * @param string $path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public function size(string $path): int|false;
}
