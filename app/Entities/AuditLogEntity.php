<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Audit Log Entity
 */
class AuditLogEntity extends Entity
{
    protected $dates = ['created_at'];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'entity_id' => 'integer',
        'old_values' => 'json-array',
        'new_values' => 'json-array',
        'metadata' => 'json-array',
    ];
}
