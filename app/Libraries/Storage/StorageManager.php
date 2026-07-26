<?php

declare(strict_types=1);

namespace App\Libraries\Storage;

use App\Libraries\Storage\Drivers\LocalDriver;
use App\Libraries\Storage\Drivers\S3Driver;

/**
 * Storage Manager
 *
 * Factory for creating storage drivers and provides a unified interface
 * for file operations across different storage backends.
 */
class StorageManager
{
    protected StorageDriverInterface $driver;
    protected string $driverName;

    public function __construct(?string $driver = null)
    {
        $this->driverName = $driver ?? config('Api')->fileStorageDriver;
        $this->driver = $this->createDriver($this->driverName);
    }

    /**
     * Create a storage driver instance
     *
     * @param string $driver Driver name (local, s3)
     * @return StorageDriverInterface
     * @throws \RuntimeException If driver is not supported
     */
    protected function createDriver(string $driver): StorageDriverInterface
    {
        return match (strtolower($driver)) {
            'local' => new LocalDriver(),
            's3' => new S3Driver(),
            default => throw new \RuntimeException("Storage driver [{$driver}] is not supported"),
        };
    }

    /**
     * Store a file
     *
     * @param string $path Path where to store the file
     * @param mixed $contents File contents (string or resource)
     * @return bool Success status
     */
    public function put(string $path, $contents): bool
    {
        return $this->driver->store($path, $contents);
    }

    /**
     * Retrieve a file
     *
     * @param string $path Path to the file
     * @return string|false File contents or false on failure
     */
    public function get(string $path): string|false
    {
        return $this->driver->retrieve($path);
    }

    /**
     * Delete a file
     *
     * @param string $path Path to the file
     * @return bool Success status
     */
    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @return bool True if exists, false otherwise
     */
    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    /**
     * Get file URL
     *
     * @param string $path Path to the file
     * @return string URL to access the file
     */
    public function url(string $path): string
    {
        return $this->driver->url($path);
    }

    /**
     * Get file size
     *
     * @param string $path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public function size(string $path): int|false
    {
        return $this->driver->size($path);
    }

    /**
     * Get the current driver name
     *
     * @return string
     */
    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * Get the underlying driver instance
     *
     * @return StorageDriverInterface
     */
    public function getDriver(): StorageDriverInterface
    {
        return $this->driver;
    }
}
