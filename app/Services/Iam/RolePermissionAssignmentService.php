<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
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
    public function __construct(
        private readonly RolePermissionModel $rolePermissionModel,
        private readonly RoleModel $roleModel,
        private readonly PermissionModel $permissionModel,
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
            $this->rolePermissionModel->insertPairs($roleId, $toAdd);
        }

        if ($toRemove !== []) {
            $this->rolePermissionModel->deletePairsForRole($roleId, $toRemove);
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
        return $this->rolePermissionModel->getPermissionIdsForRole($roleId);
    }

    private function ensureRoleExists(int $roleId): void
    {
        if (! $this->roleModel->existsById($roleId)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }

    /**
     * @param list<int> $permissionIds
     */
    private function ensurePermissionsExist(array $permissionIds): void
    {
        $foundIds = $this->permissionModel->findExistingIds($permissionIds);

        if (count(array_unique($foundIds)) !== count($permissionIds)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }
}
