<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RoleResponseDTO;
use CodeIgniter\Database\ConnectionInterface;

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
    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * @param list<string> $actorPermissions Effective permission codes the actor holds.
     * @return list<RoleResponseDTO>
     */
    public function listAssignable($actorPermissions)
    {
        $roles = $this->loadRoles();
        $rolePermissions = $this->loadRolePermissions();

        $assignable = [];
        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $codes = $rolePermissions[$roleId] ?? [];

            // Anti-escalation: every permission of the role must already be in
            // the actor's set. array_diff returns the elements of the first
            // array NOT present in the second — empty means full subset.
            if (array_diff($codes, $actorPermissions) !== []) {
                continue;
            }

            $assignable[] = new RoleResponseDTO(
                id: $roleId,
                application_id: null, // Note: AssignableRolesService currently doesn't fetch app_id in loadRoles()
                code: (string) $role['code'],
                name: (string) $role['name'],
                description: $role['description'] !== null ? (string) $role['description'] : null,
                is_system: (bool) $role['is_system'],
            );
        }

        return $assignable;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRoles(): array
    {
        $query = $this->db->table('roles r')
            ->select('r.id, r.code, r.name, r.description, r.is_system, r.is_self_assignable')
            ->orderBy('r.name', 'ASC')
            ->get();

        if ($query === false) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $query->getResultArray();
    }

    /**
     * @return array<int, list<string>> Map of role id → list of permission codes.
     */
    private function loadRolePermissions(): array
    {
        $query = $this->db->table('role_permissions rp')
            ->select('rp.role_id, p.code')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->get();

        if ($query === false) {
            return [];
        }

        $byRole = [];
        foreach ($query->getResultArray() as $row) {
            $byRole[(int) $row['role_id']][] = (string) $row['code'];
        }

        return $byRole;
    }
}
