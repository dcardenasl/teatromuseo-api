<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\DomainPermissions;

/**
 * Bootstraps the IAM tables with the system app, permissions and the three
 * system roles (superadmin, admin, user). Roles are global (cross-app) and
 * carry permissions that belong to specific applications.
 *
 * Idempotent: every insert checks for an existing row first. Safe to re-run.
 */
class RbacBootstrapSeeder extends Seeder
{
    private const APP_SELF = 'self';
    private const CMS_APP_CODE = 'cms';

    /** @var array<int, array{code: string, resource: string, action: string, description: string}> */
    private const PERMISSIONS = [
        ['code' => 'self.access',            'resource' => 'self',     'action' => 'access',            'description' => 'Baseline access to the self application'],
        ['code' => 'users.read',             'resource' => 'users',    'action' => 'read',              'description' => 'Read user records'],
        ['code' => 'users.write',            'resource' => 'users',    'action' => 'write',             'description' => 'Create, update or delete users'],
        ['code' => 'files.read',             'resource' => 'files',    'action' => 'read',              'description' => 'Read files'],
        ['code' => 'files.write',            'resource' => 'files',    'action' => 'write',             'description' => 'Upload or modify owned files'],
        ['code' => 'files.admin',            'resource' => 'files',    'action' => 'admin',             'description' => 'Manage files owned by any user'],
        ['code' => 'audit.read',             'resource' => 'audit',    'action' => 'read',              'description' => 'Read audit log entries'],
        ['code' => 'metrics.read',           'resource' => 'metrics',  'action' => 'read',              'description' => 'Read metrics dashboards'],
        ['code' => 'system.public-cache.read', 'resource' => 'public-cache', 'action' => 'read', 'description' => 'View public-site cache status'],
        ['code' => 'system.public-cache.invalidate', 'resource' => 'public-cache', 'action' => 'invalidate', 'description' => 'Invalidate public-site cache'],
        ['code' => 'apikeys.read',           'resource' => 'apikeys',  'action' => 'read',              'description' => 'Read API keys'],
        ['code' => 'apikeys.write',          'resource' => 'apikeys',  'action' => 'write',             'description' => 'Create, update or revoke API keys'],
        ['code' => 'iam.superadmin-access',  'resource' => 'iam',      'action' => 'superadmin-access', 'description' => 'Access superadmin-only operations'],
    ];

    /** @var array<int, array{code: string, name: string, description: string, permissions: array<int, string>|string, is_self_assignable: int}> */
    private const ROLES = [
        [
            'code'               => 'superadmin',
            'name'               => 'Super Administrator',
            'description'        => 'Full access to all resources and IAM operations.',
            'permissions'        => '*',
            'is_self_assignable' => 0,
        ],
        [
            'code'               => 'admin',
            'name'               => 'Administrator',
            'description'        => 'Administrative access excluding IAM and API-key mutations.',
            'permissions'        => [
                'self.access',
                'users.read', 'users.write',
                'files.read', 'files.write', 'files.admin',
                'audit.read',
                'metrics.read',
                'system.public-cache.read', 'system.public-cache.invalidate',
                'apikeys.read',
            ],
            'is_self_assignable' => 0,
        ],
        [
            'code'               => 'user',
            'name'               => 'User',
            'description'        => 'Default role for end users.',
            'permissions'        => ['self.access', 'files.read', 'files.write'],
            'is_self_assignable' => 1,
        ],
    ];

    public function run(): void
    {
        $appId = $this->ensureApplication(self::APP_SELF);
        $permissionIds = $this->ensurePermissions($appId);
        $this->ensureRoles($permissionIds);
        $this->ensureDomainApplications();
    }

    private function ensureApplication(string $name): int
    {
        $existing = $this->db->table('applications')->where('name', $name)->get()->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('applications')->insert([
            'code'       => strtolower($name),
            'name'       => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array<string, int> map of permission code → id
     */
    private function ensurePermissions(int $appId): array
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('permissions')
            ->where('application_id', $appId)
            ->get()->getResultArray();

        /** @var array<string, int> $map */
        $map = [];
        foreach ($existing as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        foreach (self::PERMISSIONS as $perm) {
            if (isset($map[$perm['code']])) {
                continue;
            }

            $this->db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $perm['code'],
                'resource'       => $perm['resource'],
                'action'         => $perm['action'],
                'description'    => $perm['description'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $map[$perm['code']] = (int) $this->db->insertID();
        }

        // Drop the legacy iam.admin-access permission if still present (deprecated).
        $this->db->table('permissions')
            ->where('application_id', $appId)
            ->where('code', 'iam.admin-access')
            ->delete();

        $this->syncCmsDomainPermissions();

        return $map;
    }

    /**
     * @param array<string, int> $permissionIds map of permission code → id
     * @return array<string, int> map of role code → id
     */
    private function ensureRoles(array $permissionIds): array
    {
        $now = date('Y-m-d H:i:s');
        /** @var array<string, int> $map */
        $map = [];

        foreach (self::ROLES as $roleDef) {
            $existing = $this->db->table('roles')
                ->where('code', $roleDef['code'])
                ->get()->getRowArray();

            if ($existing !== null) {
                $roleId = (int) $existing['id'];
                $this->db->table('roles')->where('id', $roleId)->update([
                    'name'               => $roleDef['name'],
                    'description'        => $roleDef['description'],
                    'is_system'          => 1,
                    'is_self_assignable' => $roleDef['is_self_assignable'],
                    'updated_at'         => $now,
                ]);
            } else {
                $this->db->table('roles')->insert([
                    'code'               => $roleDef['code'],
                    'name'               => $roleDef['name'],
                    'description'        => $roleDef['description'],
                    'is_system'          => 1,
                    'is_self_assignable' => $roleDef['is_self_assignable'],
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
                $roleId = (int) $this->db->insertID();
            }

            $map[$roleDef['code']] = $roleId;

            $rolePermissionIds = $roleDef['permissions'] === '*'
                ? array_values($this->allPermissionIds())
                : array_map(static fn (string $c) => $permissionIds[$c], $roleDef['permissions']);
            $this->syncRolePermissions($roleId, $rolePermissionIds);
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function allPermissionIds(): array
    {
        $rows = $this->db->table('permissions')->select('code, id')->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * Re-registers domain applications from DomainAppsRegistry when present.
     * Idempotent — skips apps that already exist by code. Permissions are NOT
     * re-seeded here; run `php spark domain:sync-permissions` on each domain
     * app after a fresh seed to restore their permission rows.
     */
    private function ensureDomainApplications(): void
    {
        if (!class_exists(\Config\DomainAppsRegistry::class)) {
            return;
        }

        $domains = \Config\DomainAppsRegistry::DOMAINS;
        if (empty($domains)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($domains as $domain) {
            $code = $domain['code'] ?? '';
            $name = $domain['name'] ?? $code;
            if ($code === '') {
                continue;
            }

            $existing = $this->db->table('applications')->where('code', $code)->get()->getRowArray();
            if ($existing === null) {
                $this->db->table('applications')->insert([
                    'code'       => $code,
                    'name'       => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Mirrors the CMS permission catalog from the domain package into the hub
     * and ensures the seeded superadmin role keeps full coverage after a reset.
     */
    private function syncCmsDomainPermissions(): void
    {
        if (! class_exists(DomainPermissions::class)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $app = $this->db->table('applications')
            ->where('code', self::CMS_APP_CODE)
            ->get()
            ->getRowArray();

        if ($app === null) {
            $this->db->table('applications')->insert([
                'code'       => self::CMS_APP_CODE,
                'name'       => self::CMS_APP_CODE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $appId = (int) $this->db->insertID();
        } else {
            $appId = (int) $app['id'];
        }

        $existing = $this->db->table('permissions')
            ->where('application_id', $appId)
            ->get()
            ->getResultArray();
        $existingCodes = [];
        foreach ($existing as $row) {
            $existingCodes[(string) $row['code']] = true;
        }

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            $code = (string) ($permission['code'] ?? '');
            if ($code === '' || isset($existingCodes[$code])) {
                continue;
            }

            $this->db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => (string) ($permission['resource'] ?? ''),
                'action'         => (string) ($permission['action'] ?? ''),
                'description'    => (string) ($permission['description'] ?? ''),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        $superadmin = $this->db->table('roles')
            ->where('code', 'superadmin')
            ->get()
            ->getRowArray();
        if ($superadmin === null) {
            return;
        }

        $permissionIds = $this->db->table('permissions')
            ->select('id')
            ->where('application_id', $appId)
            ->get()
            ->getResultArray();

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $permissionIds);
        if ($ids !== []) {
            $this->syncRolePermissions((int) $superadmin['id'], $ids);
        }
    }

    /**
     * @param array<int, int> $permissionIds
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
            foreach ($toInsert as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
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
