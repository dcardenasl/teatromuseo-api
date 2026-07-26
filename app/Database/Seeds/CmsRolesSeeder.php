<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Seeds two CMS roles that map to the two editorial audiences in the admin panel:
 *
 *   cms-editor — non-technical editors: manage entries, collections, taxonomy, and forms.
 *                Can use the wizard and the Contenido module group. No access to
 *                structural resources (pages, menus, block types, redirects).
 *
 *   cms-admin  — technical administrators: full CMS access including pages, menus,
 *                block types, redirects, languages, and settings.
 *
 * Prerequisites:
 *   - php spark db:seed RbacBootstrapSeeder  (creates the hub application + base roles)
 *   - php spark domain:sync-permissions --admin-token=<jwt>  (syncs cms.* permissions under the cms application)
 *
 * Idempotent: safe to re-run. Adds missing permissions, removes stale ones.
 */
class CmsRolesSeeder extends Seeder
{
    private const DOMAIN_APP_CODE = 'cms';

    /** Editor: day-to-day content management, no structural access */
    private const EDITOR_PERMISSIONS = [
        'cms.entries.read',
        'cms.entries.write',
        'cms.collections.read',
        'cms.categories.read',
        'cms.categories.write',
        'cms.tags.read',
        'cms.tags.write',
        'cms.forms.read',
        'cms.submissions.read',
    ];

    /** Admin: all editor permissions plus structural and configuration access */
    private const ADMIN_PERMISSIONS = [
        'cms.entries.read',
        'cms.entries.write',
        'cms.entries.admin',
        'cms.collections.read',
        'cms.collections.write',
        'cms.collections.admin',
        'cms.categories.read',
        'cms.categories.write',
        'cms.categories.admin',
        'cms.tags.read',
        'cms.tags.write',
        'cms.tags.admin',
        'cms.pages.read',
        'cms.pages.write',
        'cms.pages.admin',
        'cms.menus.read',
        'cms.menus.write',
        'cms.menus.admin',
        'cms.blocks.read',
        'cms.blocks.write',
        'cms.blocks.admin',
        'cms.redirects.read',
        'cms.redirects.write',
        'cms.languages.read',
        'cms.settings.read',
        'cms.forms.read',
        'cms.submissions.read',
        'cms.analytics.read',
    ];

    /** @var array<string, array{code: string, name: string, description: string, permissions: list<string>}> */
    private const ROLES = [
        'cms-editor' => [
            'code'        => 'cms-editor',
            'name'        => 'CMS Editor',
            'description' => 'Day-to-day content editor: manages entries, collections, taxonomy and forms. Uses the Wizard and Contenido modules. No access to structural resources.',
            'permissions' => self::EDITOR_PERMISSIONS,
        ],
        'cms-admin' => [
            'code'        => 'cms-admin',
            'name'        => 'CMS Administrator',
            'description' => 'Full CMS access including pages, menus, block types, redirects, languages and settings.',
            'permissions' => self::ADMIN_PERMISSIONS,
        ],
    ];

    public function run(): void
    {
        $permissionMap = $this->loadCmsPermissions();

        if ($permissionMap === []) {
            CLI::write('[CmsRolesSeeder] No cms.* permissions found. Run `domain:sync-permissions` first.', 'yellow');
            return;
        }

        foreach (self::ROLES as $roleDef) {
            $roleId = $this->upsertRole($roleDef);
            $permissionIds = $this->resolvePermissionIds($roleDef['permissions'], $permissionMap);
            $this->syncRolePermissions($roleId, $permissionIds);

            $label = $roleDef['name'];
            $count = count($permissionIds);
            CLI::write("[CmsRolesSeeder] Role '{$label}' seeded with {$count} permissions.", 'green');
        }
    }

    /**
     * @return array<string, int>  permission code → id for all cms.* permissions
     */
    private function loadCmsPermissions(): array
    {
        $app = $this->db->table('applications')
            ->where('code', self::DOMAIN_APP_CODE)
            ->get()->getRowArray();

        if ($app === null) {
            return [];
        }

        $rows = $this->db->table('permissions')
            ->where('application_id', (int) $app['id'])
            ->like('code', 'cms.', 'after')
            ->select('code, id')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param array{code: string, name: string, description: string, permissions: list<string>} $roleDef
     */
    private function upsertRole(array $roleDef): int
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('roles')
            ->where('code', $roleDef['code'])
            ->get()->getRowArray();

        if ($existing !== null) {
            $this->db->table('roles')->where('id', (int) $existing['id'])->update([
                'name'        => $roleDef['name'],
                'description' => $roleDef['description'],
                'updated_at'  => $now,
            ]);
            return (int) $existing['id'];
        }

        $this->db->table('roles')->insert([
            'code'               => $roleDef['code'],
            'name'               => $roleDef['name'],
            'description'        => $roleDef['description'],
            'is_system'          => 0,
            'is_self_assignable' => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param list<string>      $codes
     * @param array<string, int> $permissionMap
     * @return list<int>
     */
    private function resolvePermissionIds(array $codes, array $permissionMap): array
    {
        $ids = [];
        foreach ($codes as $code) {
            if (isset($permissionMap[$code])) {
                $ids[] = $permissionMap[$code];
            } else {
                CLI::write("[CmsRolesSeeder] Warning: permission '{$code}' not found — skipped.", 'yellow');
            }
        }
        return $ids;
    }

    /**
     * @param list<int> $permissionIds
     */
    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $existing = $this->db->table('role_permissions')
            ->where('role_id', $roleId)
            ->get()->getResultArray();
        $existingIds = array_map(static fn (array $row) => (int) $row['permission_id'], $existing);

        $toInsert = array_diff($permissionIds, $existingIds);
        $toRemove = array_diff($existingIds, $permissionIds);

        if ($toInsert !== []) {
            $rows = [];
            foreach ($toInsert as $permId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permId];
            }
            $this->db->table('role_permissions')->insertBatch($rows);
        }

        if ($toRemove !== []) {
            $this->db->table('role_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $toRemove)
                ->delete();
        }
    }
}
