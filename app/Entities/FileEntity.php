<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * File Entity
 *
 * Represents a file record with metadata
 */
class FileEntity extends Entity
{
    protected $dates = ['uploaded_at', 'deleted_at'];

    protected $casts = [
        'id'                 => 'integer',
        'user_id'            => 'integer',
        'size'               => 'integer',
        'metadata'           => 'json-array',
        'variants'           => 'json-array',
        'width'              => '?integer',
        'height'             => '?integer',
        'deleted_by_user_id' => '?integer',
    ];

    public function isTrashed(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Get human-readable file size
     *
     * @return string
     */
    public function getHumanSize(): string
    {
        $bytes = $this->size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    /**
     * Get file extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return pathinfo($this->original_name ?? '', PATHINFO_EXTENSION);
    }
}
