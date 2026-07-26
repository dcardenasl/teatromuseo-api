<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\RoleEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

class RoleModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = RoleEntity::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = ['application_id', 'code', 'name', 'description', 'is_system'];

    /** @var array<int, string> */
    protected array $searchableFields = ['code', 'name'];

    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'application_id', 'is_system', 'code'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'created_at', 'application_id', 'code', 'name', 'is_system'];

    protected $validationRules = [
        'application_id' => 'permit_empty|integer',
        'code' => 'required|string|max_length[100]',
        'name' => 'required|string|max_length[100]',
        'description' => 'permit_empty|string',
        'is_system' => 'permit_empty|in_list[0,1]',
    ];
}
