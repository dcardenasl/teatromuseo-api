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

    public function pairExists(int $userId, int $roleId): bool
    {
        return $this->where('user_id', $userId)->where('role_id', $roleId)->countAllResults() > 0;
    }

    public function assign(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        $this->insert([
            'user_id'             => $userId,
            'role_id'             => $roleId,
            'assigned_at'         => date('Y-m-d H:i:s'),
            'assigned_by_user_id' => $assignedBy,
        ]);
    }

    /**
     * @param list<int> $roleIds
     */
    public function assignMany(int $userId, array $roleIds, ?int $assignedBy = null): void
    {
        if ($roleIds === []) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = array_map(static fn (int $roleId): array => [
            'user_id'             => $userId,
            'role_id'             => $roleId,
            'assigned_at'         => $now,
            'assigned_by_user_id' => $assignedBy,
        ], $roleIds);

        $this->insertBatch($rows);
    }

    public function remove(int $userId, int $roleId): void
    {
        $this->where('user_id', $userId)->where('role_id', $roleId)->delete();
    }

    /**
     * @param list<int> $roleIds
     */
    public function removeMany(int $userId, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $this->where('user_id', $userId)->whereIn('role_id', $roleIds)->delete();
    }

    /**
     * @return list<int>
     */
    public function getRoleIdsForUser(int $userId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->select('role_id')->where('user_id', $userId)->findAll();

        return array_values(array_map(static fn (array $r): int => (int) $r['role_id'], $rows));
    }

    /**
     * The user's roles, joined with role details.
     *
     * @return list<array{id:int, code:string, name:string, description:string|null, is_system:int}>
     */
    public function getRolesForUser(int $userId): array
    {
        $query = $this->builder()
            ->select('roles.id, roles.code, roles.name, roles.description, roles.is_system')
            ->join('roles', 'roles.id = user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->orderBy('roles.name', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_map(static fn (array $r): array => [
            'id'          => (int) $r['id'],
            'code'        => (string) $r['code'],
            'name'        => (string) $r['name'],
            'description' => $r['description'] !== null ? (string) $r['description'] : null,
            'is_system'   => (int) $r['is_system'],
        ], $rows));
    }

    /**
     * Whether the user holds a role carrying the given permission code
     * (e.g. the superadmin bypass code).
     */
    public function userHasPermissionCode(int $userId, string $permissionCode): bool
    {
        $query = $this->builder()
            ->select('1', false)
            ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('user_roles.user_id', $userId)
            ->where('permissions.code', $permissionCode)
            ->limit(1)
            ->get();

        $row = $query === false ? null : $query->getRowArray();

        return $row !== null;
    }

    /**
     * Whether the user holds a role with the given role code
     * (e.g. the system 'superadmin' role).
     */
    public function userHasRoleCode(int $userId, string $roleCode): bool
    {
        $count = $this->builder()
            ->join('roles', 'roles.id = user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->where('roles.code', $roleCode)
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Permission codes granted to the user across all of their roles
     * (deduplicated).
     *
     * @return list<string>
     */
    public function getPermissionCodesForUser(int $userId): array
    {
        $query = $this->builder()
            ->select('permissions.code')
            ->distinct()
            ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('user_roles.user_id', $userId)
            ->orderBy('permissions.code', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_unique(array_map(static fn (array $r): string => (string) $r['code'], $rows)));
    }

    /**
     * Permission codes granted to the user for a specific application.
     *
     * @return list<string>
     */
    public function getPermissionCodesForUserAndApplication(int $userId, int $applicationId): array
    {
        $query = $this->builder()
            ->select('permissions.code')
            ->distinct()
            ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('user_roles.user_id', $userId)
            ->where('permissions.application_id', $applicationId)
            ->orderBy('permissions.code', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_unique(array_map(static fn (array $r): string => (string) $r['code'], $rows)));
    }
}
