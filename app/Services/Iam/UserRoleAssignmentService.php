<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserRoleModel;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Assigns and removes global roles for users via the user_roles table.
 *
 * Replaces the legacy membership_roles flow. Roles are global (cross-app);
 * effective permissions are derived per-application by joining role_permissions
 * with permissions filtered by application_id (see EffectivePermissionsResolver).
 */
class UserRoleAssignmentService
{
    private const DEFAULT_USER_ROLE_CODE = 'user';

    /**
     * CMS profiles are additive to the baseline user profile. The baseline
     * owns self.access and the normal media capabilities (files.read/write),
     * while the CMS role owns only cms.* permissions.
     *
     * @var list<string>
     */
    private const CMS_ROLE_CODES_REQUIRING_BASE_USER = [
        'cms-editor',
        'cms-editor-structure',
        'cms-admin',
    ];

    public function __construct(
        private readonly UserRoleModel $userRoleModel,
        private readonly RoleModel $roleModel,
        private readonly RolePermissionModel $rolePermissionModel,
        private readonly EffectivePermissionsResolver $effectivePermissions
    ) {
    }

    /**
     * Idempotent: inserts (user_id, role_id) only if not present.
     */
    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        if ($this->userRoleModel->pairExists($userId, $roleId)) {
            return;
        }

        $this->userRoleModel->assign($userId, $roleId, $assignedBy);

        $this->effectivePermissions->invalidateAll();
    }

    public function assignRoleByCode(int $userId, string $code, ?int $assignedBy = null): void
    {
        $this->assignRole($userId, $this->resolveRoleIdByCode($code), $assignedBy);
    }

    /**
     * Replaces the user's full role set. Used by the admin form (multi-select).
     * Anti-escalation: when $actorId is provided, every target role must be
     * fully owned (permission-wise) by the actor.
     *
     * @param list<int> $roleIds
     */
    public function syncRoles(int $userId, $roleIds, ?int $actorId = null): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roleIds = $this->ensureBaseUserRoleForCmsProfile($roleIds);

        if ($actorId !== null) {
            $this->assertActorCanGrantRoles($actorId, $roleIds);
        }

        $current = $this->userRoleModel->getRoleIdsForUser($userId);

        $toAdd    = array_values(array_diff($roleIds, $current));
        $toRemove = array_values(array_diff($current, $roleIds));

        if ($toAdd !== []) {
            $this->userRoleModel->assignMany($userId, $toAdd, $actorId);
        }

        if ($toRemove !== []) {
            $this->userRoleModel->removeMany($userId, $toRemove);
        }

        // Never leave a user with zero roles — re-assign default 'user'.
        if ($this->userRoleModel->getRoleIdsForUser($userId) === []) {
            $this->assignRoleByCode($userId, self::DEFAULT_USER_ROLE_CODE, $actorId);
        }

        $this->effectivePermissions->invalidateAll();
    }

    /**
     * Preserve the cross-application baseline when a CMS profile is selected
     * explicitly in the Admin user editor. This keeps files.read/write and
     * self.access outside the cms.* role while making the composition
     * deterministic for create and update flows.
     *
     * @param list<int> $roleIds
     * @return list<int>
     */
    private function ensureBaseUserRoleForCmsProfile(array $roleIds): array
    {
        $codes = $this->roleModel->findCodesByIds($roleIds);
        foreach ($codes as $code) {
            if (! in_array($code, self::CMS_ROLE_CODES_REQUIRING_BASE_USER, true)) {
                continue;
            }

            $baseRoleId = $this->resolveRoleIdByCode(self::DEFAULT_USER_ROLE_CODE);
            if (! in_array($baseRoleId, $roleIds, true)) {
                $roleIds[] = $baseRoleId;
            }
            break;
        }

        return array_values(array_unique($roleIds));
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $this->userRoleModel->remove($userId, $roleId);

        if ($this->userRoleModel->getRoleIdsForUser($userId) === []) {
            $this->assignRoleByCode($userId, self::DEFAULT_USER_ROLE_CODE);
        }

        $this->effectivePermissions->invalidateAll();
    }

    /**
     * @return list<array{id:int, code:string, name:string, description:string|null, is_system:int}>
     */
    public function getUserRoles(int $userId)
    {
        return $this->userRoleModel->getRolesForUser($userId);
    }

    public function isSuperadmin(int $userId): bool
    {
        return $this->userRoleModel->userHasPermissionCode($userId, 'iam.superadmin-access');
    }

    private function resolveRoleIdByCode(string $code): int
    {
        $roleId = $this->roleModel->findIdByCode($code);
        if ($roleId === null) {
            throw new NotFoundException(sprintf('Role with code "%s" not found.', $code));
        }

        return $roleId;
    }

    /**
     * Anti-escalation: actor cannot grant a role whose permissions are not a
     * subset of the actor's own permissions (across all apps the role touches).
     *
     * @param list<int> $roleIds
     */
    private function assertActorCanGrantRoles(int $actorId, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $actorPermissionCodes = $this->userRoleModel->getPermissionCodesForUser($actorId);
        $byRole               = $this->rolePermissionModel->getPermissionCodesByRoleIds($roleIds);

        foreach ($roleIds as $roleId) {
            $codes = $byRole[$roleId] ?? [];
            $diff  = array_diff($codes, $actorPermissionCodes);
            if ($diff !== []) {
                throw new AuthorizationException(sprintf(
                    'You cannot assign a role that includes permissions you do not own: %s',
                    implode(', ', $diff)
                ));
            }
        }
    }
}
