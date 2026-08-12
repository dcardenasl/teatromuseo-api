<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the explicit cross-owner file-management permission for installations
 * that were bootstrapped before the file authorization policy was tightened.
 *
 * Fresh installations receive the same permission through
 * RbacBootstrapSeeder; this migration is intentionally safe when the seed
 * data does not exist yet.
 */
final class AddFilesAdminPermission extends Migration
{
    private const APPLICATION_CODE = 'self';
    private const PERMISSION_CODE = 'files.admin';

    public function up(): void
    {
        $application = $this->db->table('applications')
            ->where('code', self::APPLICATION_CODE)
            ->get()
            ->getRowArray();

        if ($application === null) {
            return;
        }

        $applicationId = (int) $application['id'];
        $permission = $this->db->table('permissions')
            ->where('application_id', $applicationId)
            ->where('code', self::PERMISSION_CODE)
            ->get()
            ->getRowArray();

        if ($permission === null) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('permissions')->insert([
                'application_id' => $applicationId,
                'code'           => self::PERMISSION_CODE,
                'resource'       => 'files',
                'action'         => 'admin',
                'description'    => 'Manage files owned by any user',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $permissionId = (int) $this->db->insertID();
        } else {
            $permissionId = (int) $permission['id'];
        }

        foreach (['admin', 'superadmin'] as $roleCode) {
            $role = $this->db->table('roles')
                ->where('code', $roleCode)
                ->get()
                ->getRowArray();

            if ($role === null) {
                continue;
            }

            $alreadyAttached = $this->db->table('role_permissions')
                ->where('role_id', (int) $role['id'])
                ->where('permission_id', $permissionId)
                ->countAllResults() > 0;

            if (! $alreadyAttached) {
                $this->db->table('role_permissions')->insert([
                    'role_id'       => (int) $role['id'],
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $application = $this->db->table('applications')
            ->where('code', self::APPLICATION_CODE)
            ->get()
            ->getRowArray();

        if ($application === null) {
            return;
        }

        $permission = $this->db->table('permissions')
            ->where('application_id', (int) $application['id'])
            ->where('code', self::PERMISSION_CODE)
            ->get()
            ->getRowArray();

        if ($permission === null) {
            return;
        }

        $this->db->table('role_permissions')
            ->where('permission_id', (int) $permission['id'])
            ->delete();
        $this->db->table('permissions')
            ->where('id', (int) $permission['id'])
            ->delete();
    }
}
