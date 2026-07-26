<?php

declare(strict_types=1);

namespace App\Models;

class UserRoleModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    protected $table         = 'user_roles';
    protected $primaryKey    = 'user_id';
    protected $returnType    = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'role_id', 'assigned_at', 'assigned_by_user_id'];
}
