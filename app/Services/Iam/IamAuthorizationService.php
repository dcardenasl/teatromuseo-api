<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Services\System\SecurityAuditLogger;
use dcardenasl\Ci4ApiCore\Services\Iam\AbstractIamAuthorizationService;

/**
 * Hierarchical authorization rules for IAM operations.
 *
 * Inherits the policy logic (assertNotSelf, isSuperAdmin, actorPermissions,
 * assertCanGrantPermissions/Roles, assertCanModifyRole, assertCanActOnSubject,
 * assertSuperAdmin) from `AbstractIamAuthorizationService`. Provides the
 * three storage hooks that bind the abstract logic to the starter's
 * `roles` / `permissions` / `role_permissions` tables.
 *
 * Constants are exposed for callers that want to refer to canonical
 * permission codes by name rather than literal strings.
 */
class IamAuthorizationService extends AbstractIamAuthorizationService
{
    public const SUPERADMIN_PERMISSION  = 'iam.superadmin-access';
    public const ADMIN_PERMISSION       = 'iam.admin-access';
    public const DEFAULT_APPLICATION_ID = 1;

    public function __construct(
        EffectivePermissionsResolver $resolver,
        SecurityAuditLogger $audit,
        private readonly RoleModel $roleModel,
        private readonly PermissionModel $permissionModel,
        private readonly RolePermissionModel $rolePermissionModel,
    ) {
        parent::__construct($resolver, $audit);
    }

    protected function superAdminPermission(): string
    {
        return self::SUPERADMIN_PERMISSION;
    }

    protected function defaultApplicationId(): int
    {
        return self::DEFAULT_APPLICATION_ID;
    }

    protected function loadRoleSystemFlag(int $roleId): bool
    {
        return $this->roleModel->isSystemRole($roleId);
    }

    /**
     * @param array<int, int> $permissionIds
     * @return list<string>
     */
    protected function resolvePermissionCodes(array $permissionIds): array
    {
        return $this->permissionModel->findCodesByIds(array_values($permissionIds));
    }

    /**
     * @param array<int, int> $roleIds
     * @return list<string>
     */
    protected function resolveRolePermissionCodes(array $roleIds): array
    {
        $byRole = $this->rolePermissionModel->getPermissionCodesByRoleIds(array_values($roleIds));

        $codes = [];
        foreach ($byRole as $roleCodes) {
            foreach ($roleCodes as $code) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }
}
