<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Removes foreign-namespace permissions that were mirrored under the 'self'
 * application by `domain:sync-permissions --mirror-to-self`.
 *
 * Since `EffectivePermissionsResolver::resolveAll()` now aggregates permissions
 * across all applications, the mirror copies in 'self' are redundant and
 * potentially confusing. This command cleans them up.
 *
 * Idempotent: safe to re-run; does nothing if no mirrored permissions exist.
 *
 * Usage:
 *   php spark iam:remove-mirrored-permissions
 *   php spark iam:remove-mirrored-permissions --dry-run
 */
class RemoveMirroredPermissions extends BaseCommand
{
    protected $group = 'IAM';
    protected $name = 'iam:remove-mirrored-permissions';
    protected $description = 'Remove foreign-namespace permission copies from the self application (cleanup after --mirror-to-self).';

    protected $usage = 'iam:remove-mirrored-permissions [--dry-run]';
    protected $options = [
        '--dry-run' => 'Preview what would be removed without making any changes.',
    ];

    public function run(array $params): void
    {
        $dryRun = CLI::getOption('dry-run') === true;

        $db = \Config\Database::connect();

        $selfAppResult = $db->table('applications')->where('code', 'self')->get();
        $selfApp       = $selfAppResult === false ? null : $selfAppResult->getRowArray();
        if ($selfApp === null) {
            CLI::write('[iam:remove-mirrored-permissions] Application "self" not found. Nothing to do.', 'yellow');
            return;
        }
        $selfAppId = (int) $selfApp['id'];

        // Collect all non-self app codes → namespace prefixes
        $otherAppsResult = $db->table('applications')->where('code !=', 'self')->get();
        $otherApps       = $otherAppsResult === false ? [] : $otherAppsResult->getResultArray();
        if ($otherApps === []) {
            CLI::write('[iam:remove-mirrored-permissions] No other applications found. Nothing to do.', 'green');
            return;
        }

        $prefixes = array_map(static fn (array $app) => (string) $app['code'] . '.', $otherApps);

        // Find all permissions under 'self' whose code starts with a foreign prefix
        $selfPermsResult = $db->table('permissions')
            ->where('application_id', $selfAppId)
            ->select('id, code')
            ->get();
        $selfPerms       = $selfPermsResult === false ? [] : $selfPermsResult->getResultArray();

        $toRemove = array_filter($selfPerms, static function (array $perm) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with((string) $perm['code'], $prefix)) {
                    return true;
                }
            }
            return false;
        });

        if ($toRemove === []) {
            CLI::write('[iam:remove-mirrored-permissions] No mirrored permissions found. Nothing to do.', 'green');
            return;
        }

        $ids = array_values(array_map(static fn (array $p) => (int) $p['id'], $toRemove));

        CLI::write(sprintf('[iam:remove-mirrored-permissions] Found %d mirrored permission(s) under "self":', count($ids)), $dryRun ? 'yellow' : 'red');
        foreach ($toRemove as $perm) {
            CLI::write('  - ' . (string) $perm['code'], 'dark_gray');
        }

        if ($dryRun) {
            CLI::write('[iam:remove-mirrored-permissions] Dry-run: no changes made.', 'yellow');
            return;
        }

        $db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
        $db->table('permissions')->whereIn('id', $ids)->delete();

        Services::effectivePermissionsResolver()->invalidateAll();

        CLI::write(sprintf('[iam:remove-mirrored-permissions] Removed %d permission(s) and their role assignments. Cache cleared.', count($ids)), 'green');
    }
}
