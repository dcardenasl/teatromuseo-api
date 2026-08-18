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

    /**
     * Single-read projection for the authenticated role editor workspace.
     *
     * @return list<array<string, mixed>>
     */
    public function findWorkspaceRows(int $roleId, int $limit = 2000): array
    {
        $query = $this->builder()
            ->select('roles.id, roles.code, roles.name, roles.description, roles.is_system,
                      roles.created_at, roles.updated_at,
                      permissions.id AS permission_id, permissions.application_id AS permission_application_id,
                      permissions.code AS permission_code, permissions.resource AS permission_resource,
                      permissions.action AS permission_action, permissions.description AS permission_description,
                      permissions.created_at AS permission_created_at, permissions.updated_at AS permission_updated_at,
                      permission_apps.name AS permission_application_name,
                      role_permissions.permission_id AS assigned_permission_id')
            ->join('permissions', '1 = 1', 'left', false)
            ->join('applications permission_apps', 'permission_apps.id = permissions.application_id', 'left')
            ->join('role_permissions', 'role_permissions.role_id = roles.id AND role_permissions.permission_id = permissions.id', 'left', false)
            ->where('roles.id', $roleId)
            ->orderBy('permissions.code', 'ASC')
            ->limit($limit);
        $result = $query->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    /**
     * Resolve a role's primary key by its unique code.
     */
    public function findIdByCode(string $code): ?int
    {
        /** @var RoleEntity|null $role */
        $role = $this->select('id')->where('code', $code)->first();

        return $role !== null ? (int) $role->id : null;
    }

    public function existsById(int $id): bool
    {
        return $this->select('id')->find($id) !== null;
    }

    public function isSystemRole(int $id): bool
    {
        /** @var RoleEntity|null $role */
        $role = $this->select('is_system')->find($id);

        return $role !== null && (bool) $role->is_system;
    }

    /**
     * All roles, ordered by name, with the columns needed to build the
     * assignable-roles / role-permission-matrix read models.
     *
     * @return list<array{id:int, code:string, name:string, description:string|null, is_system:bool, is_self_assignable:bool}>
     */
    public function listAllOrderedByName(): array
    {
        /** @var list<RoleEntity> $roles */
        $roles = $this->select('id, code, name, description, is_system, is_self_assignable')
            ->orderBy('name', 'ASC')
            ->findAll();

        return array_map(static fn (RoleEntity $role): array => [
            'id'                 => (int) $role->id,
            'code'               => (string) $role->code,
            'name'               => (string) $role->name,
            'description'        => $role->description !== null ? (string) $role->description : null,
            'is_system'          => (bool) $role->is_system,
            'is_self_assignable' => (bool) ($role->is_self_assignable ?? false),
        ], $roles);
    }

    /**
     * All roles, ordered by code — used by the role-permission matrix (which
     * doesn't need is_self_assignable).
     *
     * @return list<array{id:int, code:string, name:string, description:string|null, is_system:bool}>
     */
    public function listAllOrderedByCode(): array
    {
        /** @var list<RoleEntity> $roles */
        $roles = $this->select('id, code, name, description, is_system')
            ->orderBy('code', 'ASC')
            ->findAll();

        return array_map(static fn (RoleEntity $role): array => [
            'id'          => (int) $role->id,
            'code'        => (string) $role->code,
            'name'        => (string) $role->name,
            'description' => $role->description !== null ? (string) $role->description : null,
            'is_system'   => (bool) $role->is_system,
        ], $roles);
    }
}
