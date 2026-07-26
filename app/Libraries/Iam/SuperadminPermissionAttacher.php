<?php

declare(strict_types=1);

namespace App\Libraries\Iam;

use CodeIgniter\Database\ConnectionInterface;
use Config\Services;

final class SuperadminPermissionAttacher
{
    /**
     * @var ConnectionInterface<object, object>
     */
    private ConnectionInterface $db;

    /**
     * @param ConnectionInterface<object, object>|null $db
     */
    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * @param list<int> $permissionIds
     */
    public function attach(array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $permissionIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($permissionIds === []) {
            return;
        }

        $roleQuery = $this->db->table('roles')->select('id')->where('code', 'superadmin')->get();
        if ($roleQuery === false) {
            return;
        }

        $role = $roleQuery->getRowArray();
        if ($role === null) {
            return;
        }

        $roleId = (int) $role['id'];
        $existingQuery = $this->db->table('role_permissions')
            ->select('permission_id')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->get();
        $existing = $existingQuery === false ? [] : $existingQuery->getResultArray();

        $existingIds = array_map(static fn (array $row): int => (int) $row['permission_id'], $existing);
        $missingIds = array_values(array_diff($permissionIds, $existingIds));

        if ($missingIds !== []) {
            $rows = array_map(
                static fn (int $permissionId): array => ['role_id' => $roleId, 'permission_id' => $permissionId],
                $missingIds
            );
            $this->db->table('role_permissions')->insertBatch($rows);
        }

        Services::effectivePermissionsResolver()->invalidateAll();
    }
}
