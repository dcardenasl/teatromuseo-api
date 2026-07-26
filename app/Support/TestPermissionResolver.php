<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Looks up effective permission codes for a role across all applications for
 * use in test contexts where membership rows may not exist yet.
 *
 * Production code MUST NOT depend on this — it's a transition aid that lets
 * the test suite exercise permission-protected routes after the legacy
 * role-based filter has been replaced. Real auth flows resolve permissions
 * via EffectivePermissionsResolver against actual membership rows.
 */
final class TestPermissionResolver
{
    /**
     * @return list<string>
     */
    public static function permissionsForRole(string $role): array
    {
        try {
            $db = \Config\Database::connect();
        } catch (\Throwable) {
            return [];
        }

        try {
            $query = $db->table('roles r')
                ->select('p.code')
                ->distinct()
                ->join('role_permissions rp', 'rp.role_id = r.id')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->where('r.code', $role)
                ->orderBy('p.code', 'ASC')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        if ($query === false) {
            return [];
        }

        $rows = $query->getResultArray();

        return array_values(array_unique(array_map(static fn (array $row) => (string) $row['code'], $rows)));
    }
}
