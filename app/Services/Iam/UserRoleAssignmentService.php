<?php

declare(strict_types=1);

namespace App\Services\Iam;

use CodeIgniter\Database\ConnectionInterface;
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
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly EffectivePermissionsResolver $effectivePermissions
    ) {
    }

    /**
     * Idempotent: inserts (user_id, role_id) only if not present.
     */
    public function assignRole(int $userId, int $roleId, ?int $assignedBy = null): void
    {
        $exists = $this->db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->countAllResults() > 0;

        if ($exists) {
            return;
        }

        $this->db->table('user_roles')->insert([
            'user_id'             => $userId,
            'role_id'             => $roleId,
            'assigned_at'         => date('Y-m-d H:i:s'),
            'assigned_by_user_id' => $assignedBy,
        ]);

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

        if ($actorId !== null) {
            $this->assertActorCanGrantRoles($actorId, $roleIds);
        }

        $current = $this->getRoleIds($userId);

        $toAdd    = array_diff($roleIds, $current);
        $toRemove = array_diff($current, $roleIds);

        if ($toAdd !== []) {
            $now = date('Y-m-d H:i:s');
            $rows = [];
            foreach ($toAdd as $roleId) {
                $rows[] = [
                    'user_id'             => $userId,
                    'role_id'             => (int) $roleId,
                    'assigned_at'         => $now,
                    'assigned_by_user_id' => $actorId,
                ];
            }
            $this->db->table('user_roles')->insertBatch($rows);
        }

        if ($toRemove !== []) {
            $this->db->table('user_roles')
                ->where('user_id', $userId)
                ->whereIn('role_id', array_map('intval', $toRemove))
                ->delete();
        }

        // Never leave a user with zero roles — re-assign default 'user'.
        if ($this->getRoleIds($userId) === []) {
            $this->assignRoleByCode($userId, self::DEFAULT_USER_ROLE_CODE, $actorId);
        }

        $this->effectivePermissions->invalidateAll();
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $this->db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();

        if ($this->getRoleIds($userId) === []) {
            $this->assignRoleByCode($userId, self::DEFAULT_USER_ROLE_CODE);
        }

        $this->effectivePermissions->invalidateAll();
    }

    /**
     * @return list<array{id:int, code:string, name:string, description:string|null, is_system:int}>
     */
    public function getUserRoles(int $userId)
    {
        $result = $this->db->table('user_roles ur')
            ->select('r.id, r.code, r.name, r.description, r.is_system')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->orderBy('r.name', 'ASC')
            ->get();

        $rows = $result !== false ? $result->getResultArray() : [];

        return array_values(array_map(static fn (array $r) => [
            'id'          => (int) $r['id'],
            'code'        => (string) $r['code'],
            'name'        => (string) $r['name'],
            'description' => $r['description'] !== null ? (string) $r['description'] : null,
            'is_system'   => (int) $r['is_system'],
        ], $rows));
    }

    public function isSuperadmin(int $userId): bool
    {
        $result = $this->db->table('user_roles ur')
            ->select('1', false)
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('p.code', 'iam.superadmin-access')
            ->limit(1)
            ->get();

        $row = $result !== false ? $result->getRowArray() : null;

        return $row !== null;
    }

    /**
     * @return list<int>
     */
    private function getRoleIds(int $userId): array
    {
        $result = $this->db->table('user_roles')
            ->select('role_id')
            ->where('user_id', $userId)
            ->get();

        $rows = $result !== false ? $result->getResultArray() : [];

        return array_values(array_map(static fn (array $r) => (int) $r['role_id'], $rows));
    }

    private function resolveRoleIdByCode(string $code): int
    {
        $result = $this->db->table('roles')->where('code', $code)->limit(1)->get();
        $row    = $result !== false ? $result->getRowArray() : null;
        if ($row === null) {
            throw new NotFoundException(sprintf('Role with code "%s" not found.', $code));
        }
        return (int) $row['id'];
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

        $actorPermissionCodes = $this->getUserPermissionCodes($actorId);

        $rpcResult           = $this->db->table('role_permissions rp')
            ->select('rp.role_id, p.code')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->whereIn('rp.role_id', $roleIds)
            ->get();
        $rolePermissionCodes = $rpcResult !== false ? $rpcResult->getResultArray() : [];

        $byRole = [];
        foreach ($rolePermissionCodes as $row) {
            $byRole[(int) $row['role_id']][] = (string) $row['code'];
        }

        foreach ($roleIds as $roleId) {
            $codes = $byRole[$roleId] ?? [];
            $diff = array_diff($codes, $actorPermissionCodes);
            if ($diff !== []) {
                throw new AuthorizationException(sprintf(
                    'You cannot assign a role that includes permissions you do not own: %s',
                    implode(', ', $diff)
                ));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getUserPermissionCodes(int $userId): array
    {
        $result = $this->db->table('user_roles ur')
            ->select('p.code')
            ->distinct()
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->get();

        $rows = $result !== false ? $result->getResultArray() : [];

        return array_values(array_unique(array_map(static fn (array $r) => (string) $r['code'], $rows)));
    }
}
