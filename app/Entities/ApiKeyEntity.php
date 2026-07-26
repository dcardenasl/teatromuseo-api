<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * API Key Entity
 *
 * Represents an API key in the system with rate limiting configuration.
 * The raw key is never stored; only the prefix and SHA-256 hash are persisted.
 */
class ApiKeyEntity extends Entity
{
    /**
     * @var array<string, string> Field to attribute mapping
     */
    protected $datamap = [];

    /**
     * @var list<string> Date fields for automatic conversion
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string> Type casting for attributes
     */
    protected $casts = [
        'id'                   => 'integer',
        'application_id'       => '?integer',
        'is_active'            => 'boolean',
        'rate_limit_requests'  => 'integer',
        'rate_limit_window'    => 'integer',
        'user_rate_limit'      => 'integer',
        'ip_rate_limit'        => 'integer',
    ];

    /**
     * Convert entity to array
     *
     * @param bool $onlyChanged Return only changed fields
     * @param bool $cast        Apply casting
     * @param bool $recursive   Recursively convert nested entities
     * @return array<string, mixed>
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return parent::toArray($onlyChanged, $cast, $recursive);
    }

    /**
     * Check whether the API key is currently active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
