<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RoleResponseDTO;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;

/**
 * AssignableRolesService
 *
 * Resolves the global roles an actor is allowed to assign to OTHER users
 * without escalating privilege. The rule: a role is assignable iff every
 * permission code attached to that role is already in the actor's
 * effective permission set for the current application.
 *
 * Audit B7.1 (2026-05-06): extracted from `UserController::assignableRoles`,
 * which executed raw queries + filtering inside the controller — a layer
 * violation that made the rule untestable in isolation. The controller now
 * delegates here and stays declarative.
 *
 * **Anti-escalation contract:** if `array_diff(rolePermissions, actorPermissions)`
 * is non-empty, the role would grant the target a permission the actor
 * does not hold — that is escalation. Such roles are filtered out.
 */
readonly class AssignableRolesService
{
    public function __construct(
        private RoleModel $roleModel,
        private RolePermissionModel $rolePermissionModel
    ) {
    }

    /**
     * @param list<string> $actorPermissions Effective permission codes the actor holds.
     * @return list<RoleResponseDTO>
     */
    public function listAssignable($actorPermissions)
    {
        $roles = $this->roleModel->listAllOrderedByName();
        $rolePermissions = $this->rolePermissionModel->getAllPermissionCodesByRole();

        $assignable = [];
        foreach ($roles as $role) {
            $roleId = $role['id'];
            $codes = $rolePermissions[$roleId] ?? [];

            // Anti-escalation: every permission of the role must already be in
            // the actor's set. array_diff returns the elements of the first
            // array NOT present in the second — empty means full subset.
            if (array_diff($codes, $actorPermissions) !== []) {
                continue;
            }

            $assignable[] = new RoleResponseDTO(
                id: $roleId,
                application_id: null, // Note: AssignableRolesService currently doesn't fetch app_id
                code: $role['code'],
                name: $role['name'],
                description: $role['description'],
                is_system: $role['is_system'],
            );
        }

        return $assignable;
    }
}
