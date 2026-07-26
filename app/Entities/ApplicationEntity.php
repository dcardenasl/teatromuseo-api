<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ApplicationEntity extends Entity
{
    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
    ];

    /** @var list<string> */
    protected $dates = ['created_at', 'updated_at'];
}
