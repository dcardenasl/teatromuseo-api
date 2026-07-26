<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ApplicationEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

class ApplicationModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table            = 'applications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ApplicationEntity::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = ['name'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @var array<int, string> */
    protected array $searchableFields = ['name'];

    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'name'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'name', 'created_at'];
}
