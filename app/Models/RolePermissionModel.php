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

    /**
     * @return list<int>
     */
    public function getPermissionIdsForRole(int $roleId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->select('permission_id')->where('role_id', $roleId)->findAll();

        return array_values(array_map(static fn (array $r): int => (int) $r['permission_id'], $rows));
    }

    /**
     * @param list<int> $permissionIds
     */
    public function insertPairs(int $roleId, array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $rows = array_map(
            static fn (int $permissionId): array => ['role_id' => $roleId, 'permission_id' => $permissionId],
            $permissionIds
        );

        $this->insertBatch($rows);
    }

    /**
     * Detach permissions from a role. Pass null to detach all.
     *
     * @param list<int>|null $permissionIds
     */
    public function deletePairsForRole(int $roleId, ?array $permissionIds = null): void
    {
        $builder = $this->where('role_id', $roleId);

        if ($permissionIds !== null) {
            if ($permissionIds === []) {
                return;
            }
            $builder = $builder->whereIn('permission_id', $permissionIds);
        }

        $builder->delete();
    }

    public function deletePair(int $roleId, int $permissionId): void
    {
        $this->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
    }

    /**
     * Permission codes attached to each of the given roles.
     *
     * @param list<int> $roleIds
     * @return array<int, list<string>> keyed by role_id
     */
    public function getPermissionCodesByRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $query = $this->builder()
            ->select('role_permissions.role_id, permissions.code')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        $byRole = [];
        foreach ($rows as $row) {
            $byRole[(int) $row['role_id']][] = (string) $row['code'];
        }

        return $byRole;
    }

    /**
     * Permission codes attached to every role in the system.
     *
     * @return array<int, list<string>> keyed by role_id
     */
    public function getAllPermissionCodesByRole(): array
    {
        $query = $this->builder()
            ->select('role_permissions.role_id, permissions.code')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        $byRole = [];
        foreach ($rows as $row) {
            $byRole[(int) $row['role_id']][] = (string) $row['code'];
        }

        return $byRole;
    }

    /**
     * Full permission detail (joined with the owning application's name) for
     * every permission attached to a role, ordered by code.
     *
     * @return list<array<string, mixed>>
     */
    public function getDetailedPermissionsForRole(int $roleId): array
    {
        $query = $this->builder()
            ->select('permissions.id, permissions.application_id, applications.name AS application_name, permissions.code, permissions.resource, permissions.action, permissions.description, permissions.created_at, permissions.updated_at')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->join('applications', 'applications.id = permissions.application_id', 'left')
            ->where('role_permissions.role_id', $roleId)
            ->orderBy('permissions.code', 'ASC')
            ->get();

        return $query === false ? [] : array_values($query->getResultArray());
    }

    /**
     * Every role↔permission assignment in the system, grouped by role.
     *
     * @return array<int, list<int>> keyed by role_id
     */
    public function allAssignmentsGroupedByRole(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->orderBy('role_id', 'ASC')->orderBy('permission_id', 'ASC')->findAll();

        $assignments = [];
        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            $assignments[$roleId] ??= [];
            $assignments[$roleId][] = (int) $row['permission_id'];
        }

        return $assignments;
    }
}
