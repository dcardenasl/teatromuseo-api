<?php

declare(strict_types=1);

namespace App\Models;

class RolePermissionModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    /** @var string */
    protected $table            = 'role_permissions';
    protected $primaryKey       = 'role_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = ['role_id', 'permission_id'];
}
