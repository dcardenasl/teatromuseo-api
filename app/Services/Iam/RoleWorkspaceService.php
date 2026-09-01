<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RoleWorkspaceResponseDTO;
use App\Models\RoleModel;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Read-only role editor projection owned by the Hub.
 *
 * One bounded JOIN returns the role, its application label, the permission
 * catalogue and the role-permission marker. The Admin can therefore render
 * show/edit without rebuilding the IAM graph through three HTTP calls.
 */
final class RoleWorkspaceService
{
    private const MAX_PERMISSIONS = 2000;

    public function __construct(private readonly RoleModel $roles)
    {
    }

    public function read(int $roleId): RoleWorkspaceResponseDTO
    {
        if ($roleId < 1) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $rows = $this->roles->findWorkspaceRows($roleId, self::MAX_PERMISSIONS);
        if ($rows === []) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $first = $rows[0];
        $role = [
            'id' => (int) $first['id'],
            'application_id' => null,
            'application_name' => null,
            'code' => (string) $first['code'],
            'name' => (string) $first['name'],
            'description' => $first['description'] === null ? null : (string) $first['description'],
            'is_system' => (bool) $first['is_system'],
            'created_at' => $first['created_at'] === null ? null : (string) $first['created_at'],
            'updated_at' => $first['updated_at'] === null ? null : (string) $first['updated_at'],
        ];

        $allPermissions = [];
        $assignedPermissionIds = [];
        foreach ($rows as $row) {
            if ($row['permission_id'] === null) {
                continue;
            }

            $permission = [
                'id' => (int) $row['permission_id'],
                'application_id' => (int) $row['permission_application_id'],
                'application_name' => $row['permission_application_name'] === null ? null : (string) $row['permission_application_name'],
                'code' => (string) $row['permission_code'],
                'resource' => (string) $row['permission_resource'],
                'action' => (string) $row['permission_action'],
                'description' => (string) ($row['permission_description'] ?? ''),
                'created_at' => $row['permission_created_at'] === null ? null : (string) $row['permission_created_at'],
                'updated_at' => $row['permission_updated_at'] === null ? null : (string) $row['permission_updated_at'],
            ];
            $allPermissions[] = $permission;
            if ($row['assigned_permission_id'] !== null) {
                $assignedPermissionIds[] = (int) $row['assigned_permission_id'];
            }
        }

        return new RoleWorkspaceResponseDTO(
            role: $role,
            allPermissions: $allPermissions,
            assignedPermissionIds: array_values(array_unique($assignedPermissionIds)),
        );
    }
}
