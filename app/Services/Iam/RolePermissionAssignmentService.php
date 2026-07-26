<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use CodeIgniter\Database\ConnectionInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Replaces a role's permission set atomically (delete missing + insert new),
 * mirroring UserRoleAssignmentService::syncRoles for the role↔permission M2M.
 *
 * Used by RoleService::store() and RoleService::update() to consume the
 * `permission_ids` array on Role create/update DTOs in a single request.
 *
 * Anti-escalation: when $context is provided, the actor must own every
 * permission being added or removed (delegated to IamAuthorizationService).
 */
class RolePermissionAssignmentService
{
    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly IamAuthorizationService $authz,
        private readonly EffectivePermissionsResolver $effectivePermissions
    ) {
    }

    /**
     * Replace the role's full permission set.
     */
    public function syncPermissions(int $roleId, AttachPermissionsRequestDTO $request, ?SecurityContext $context = null): void
    {
        $permissionIds = array_values($request->permission_ids);

        $this->ensureRoleExists($roleId);

        if ($permissionIds !== []) {
            $this->ensurePermissionsExist($permissionIds);
        }

        $current = $this->getPermissionIds($roleId);

        $toAdd    = array_values(array_diff($permissionIds, $current));
        $toRemove = array_values(array_diff($current, $permissionIds));

        // Anti-escalation: actor must own every permission being touched
        // (added OR removed). Delegated to the existing authz service.
        if ($toAdd !== []) {
            $this->authz->assertCanGrantPermissions($context, $toAdd);
        }
        if ($toRemove !== []) {
            $this->authz->assertCanGrantPermissions($context, $toRemove);
        }

        if ($toAdd !== []) {
            $rows = array_map(
                static fn (int $pid) => ['role_id' => $roleId, 'permission_id' => $pid],
                $toAdd
            );
            $this->db->table('role_permissions')->insertBatch($rows);
        }

        if ($toRemove !== []) {
            $this->db->table('role_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $toRemove)
                ->delete();
        }

        if ($toAdd !== [] || $toRemove !== []) {
            $this->effectivePermissions->invalidateAll();
        }
    }

    /**
     * @return list<int>
     */
    public function getPermissionIds(int $roleId)
    {
        $result = $this->db->table('role_permissions')
            ->select('permission_id')
            ->where('role_id', $roleId)
            ->get();

        $rows = $result === false ? [] : $result->getResultArray();

        return array_values(array_map(static fn (array $r) => (int) $r['permission_id'], $rows));
    }

    private function ensureRoleExists(int $roleId): void
    {
        $result = $this->db->table('roles')->where('id', $roleId)->select('id')->limit(1)->get();
        $row    = $result === false ? null : $result->getRowArray();
        if ($row === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }

    /**
     * @param list<int> $permissionIds
     */
    private function ensurePermissionsExist(array $permissionIds): void
    {
        $result = $this->db->table('permissions')
            ->whereIn('id', $permissionIds)
            ->select('id')->get();

        $rows = $result === false ? [] : $result->getResultArray();
        $foundIds = array_map(static fn (array $r) => (int) $r['id'], $rows);

        if (count(array_unique($foundIds)) !== count($permissionIds)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }
}
