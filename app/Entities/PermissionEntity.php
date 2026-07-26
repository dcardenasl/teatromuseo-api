<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class PermissionEntity extends Entity
{
    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'application_id' => 'integer',
    ];

    /** @var list<string> */
    protected $dates = ['created_at', 'updated_at'];
}
