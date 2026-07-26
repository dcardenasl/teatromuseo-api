<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RolePermissionMatrixResponseDTO;
use CodeIgniter\Database\ConnectionInterface;

final class RolePermissionMatrixService
{
    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function matrix(): RolePermissionMatrixResponseDTO
    {
        $applications = $this->loadApplications();
        $roles = $this->loadRoles();
        $assignments = $this->loadAssignments();

        return new RolePermissionMatrixResponseDTO($applications, $roles, $assignments);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadApplications(): array
    {
        $appQuery = $this->db->table('applications')
            ->select('id, code, name')
            ->orderBy('code', 'ASC')
            ->get();
        $appRows = $appQuery === false ? [] : $appQuery->getResultArray();

        $permissionQuery = $this->db->table('permissions')
            ->select('id, application_id, code, resource, action, description')
            ->orderBy('application_id', 'ASC')
            ->orderBy('resource', 'ASC')
            ->orderBy('action', 'ASC')
            ->get();
        $permissions = $permissionQuery === false ? [] : $permissionQuery->getResultArray();

        $byApplication = [];
        foreach ($permissions as $permission) {
            $appId = (int) $permission['application_id'];
            $byApplication[$appId][] = [
                'id'          => (int) $permission['id'],
                'code'        => (string) $permission['code'],
                'resource'    => (string) $permission['resource'],
                'action'      => (string) $permission['action'],
                'description' => (string) ($permission['description'] ?? ''),
            ];
        }

        return array_values(array_map(static fn (array $app): array => [
            'id'          => (int) $app['id'],
            'code'        => (string) $app['code'],
            'name'        => (string) $app['name'],
            'permissions' => $byApplication[(int) $app['id']] ?? [],
        ], $appRows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRoles(): array
    {
        $query = $this->db->table('roles')
            ->select('id, code, name, description, is_system')
            ->orderBy('code', 'ASC')
            ->get();
        $rows = $query === false ? [] : $query->getResultArray();

        return array_values(array_map(static fn (array $role): array => [
            'id'          => (int) $role['id'],
            'code'        => (string) $role['code'],
            'name'        => (string) $role['name'],
            'description' => (string) ($role['description'] ?? ''),
            'is_system'   => (bool) $role['is_system'],
        ], $rows));
    }

    /**
     * @return array<int, list<int>>
     */
    private function loadAssignments(): array
    {
        $query = $this->db->table('role_permissions')
            ->select('role_id, permission_id')
            ->orderBy('role_id', 'ASC')
            ->orderBy('permission_id', 'ASC')
            ->get();
        $rows = $query === false ? [] : $query->getResultArray();

        $assignments = [];
        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            $assignments[$roleId] ??= [];
            $assignments[$roleId][] = (int) $row['permission_id'];
        }

        return $assignments;
    }
}
