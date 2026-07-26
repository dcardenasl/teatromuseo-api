<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Iam\SuperadminPermissionAttacher;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\DomainPermissions;
use Config\Services;

class SyncOwnPermissions extends BaseCommand
{
    protected $group       = 'IAM';
    protected $name        = 'iam:sync-permissions';
    protected $description = 'Sync this hub application permission catalog and attach every permission to superadmin.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $appResult = $db->table('applications')->where('code', 'self')->get();
        $app       = $appResult === false ? null : $appResult->getRowArray();
        if ($app === null) {
            CLI::error('Application "self" not found. Run db:seed RbacBootstrapSeeder first.');
            return EXIT_ERROR;
        }

        $appId = (int) $app['id'];
        $created = 0;
        $existing = 0;
        $permissionIds = [];
        $now = date('Y-m-d H:i:s');

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            $code = (string) ($permission['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $permResult = $db->table('permissions')
                ->select('id')
                ->where('application_id', $appId)
                ->where('code', $code)
                ->get();
            $row        = $permResult === false ? null : $permResult->getRowArray();

            if ($row !== null) {
                $existing++;
                $permissionIds[] = (int) $row['id'];
                continue;
            }

            $db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => (string) ($permission['resource'] ?? ''),
                'action'         => (string) ($permission['action'] ?? ''),
                'description'    => (string) ($permission['description'] ?? ''),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            $created++;
            $permissionIds[] = (int) $db->insertID();
        }

        (new SuperadminPermissionAttacher($db))->attach($permissionIds);
        Services::effectivePermissionsResolver()->invalidateAll();

        CLI::write("Synced own permissions: created={$created}, existing={$existing}.", 'green');

        return EXIT_SUCCESS;
    }
}
