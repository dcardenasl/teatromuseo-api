<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Iam\SuperadminPermissionAttacher;
use App\Services\Tokens\Support\ApiKeyMaterialService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Creates a new application + its baseline `<code>.access` permission and
 * optionally attaches that permission to the seeded `user` role so all
 * existing end-users can immediately reach the new app.
 *
 * The role-permission graph for the rest of the new app's resources (CRUD,
 * read/write splits, etc.) is curated separately by the superadmin via the
 * IAM UI.
 */
class BootstrapApplication extends BaseCommand
{
    protected $group       = 'IAM';
    protected $name        = 'apps:bootstrap';
    protected $description = 'Create a new application, its <code>.access permission, and optionally grant it to the user role.';
    protected $usage       = 'apps:bootstrap <code> [--name="..."] [--no-grant-user] [--create-api-key] [--api-key-name="..."]';
    protected $arguments   = [
        'code' => 'The application code (slug). Used as the prefix for permissions, e.g. blog → blog.access.',
    ];
    protected $options     = [
        '--name'           => 'Display name for the application. Defaults to the code (capitalized).',
        '--no-grant-user'  => 'Skip granting <code>.access to the user role (no interactive prompt).',
        '--create-api-key' => 'Generate an active API key bound to the application. Emits API_KEY=apk_... and APP_ID=N to stdout.',
        '--api-key-name'   => 'Name for the generated API key. Defaults to "<code>-app-key".',
    ];

    public function run(array $params)
    {
        $code = strtolower(trim((string) ($params[0] ?? '')));
        if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)) {
            CLI::error('Provide a slug-style application code (lowercase letters, digits, dashes, underscores).');
            return EXIT_ERROR;
        }

        $name = (string) (CLI::getOption('name') ?: ucfirst($code));
        $skipGrant = (bool) CLI::getOption('no-grant-user');
        $createApiKey = (bool) CLI::getOption('create-api-key');
        $apiKeyName = (string) (CLI::getOption('api-key-name') ?: "{$code}-app-key");

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $appId = $this->ensureApplication($db, $code, $name, $now);
        CLI::write("✓ Application '{$code}' (id={$appId}) ready.", 'green');

        $permId = $this->ensurePermission($db, $appId, $code, $now);
        (new SuperadminPermissionAttacher($db))->attach([$permId]);
        CLI::write("✓ Permission '{$code}.access' (id={$permId}) ready.", 'green');

        $shouldGrant = ! $skipGrant && CLI::prompt(
            "Grant '{$code}.access' to the 'user' role so every registered user can access this app?",
            ['y', 'n'],
            'required'
        ) === 'y';

        if ($shouldGrant) {
            $userRoleId = $this->resolveUserRoleId($db);
            if ($userRoleId === null) {
                CLI::error('Could not find the seeded "user" role. Run "php spark db:seed RbacBootstrapSeeder" first.');
                return EXIT_ERROR;
            }

            $this->ensureRolePermission($db, $userRoleId, $permId);
            CLI::write("✓ '{$code}.access' attached to the 'user' role.", 'green');
        } else {
            CLI::write("• Skipped granting '{$code}.access' to the 'user' role.", 'yellow');
        }

        if ($createApiKey) {
            $existing = $this->findActiveApiKeyForApplication($db, $appId);
            if ($existing !== null) {
                CLI::error("An active API key already exists for application '{$code}' (id={$existing['id']}, prefix={$existing['key_prefix']}).");
                CLI::error('The raw key is unrecoverable. Revoke it via the IAM UI and re-run, or pass a fresh --code.');
                CLI::write("API_KEY_EXISTS={$existing['key_prefix']}");
                CLI::write("APP_ID={$appId}");

                return EXIT_ERROR;
            }

            $rawKey = $this->createApiKey($db, $appId, $apiKeyName, $now);
            CLI::write("✓ API key '{$apiKeyName}' created and bound to application id={$appId}.", 'green');
            CLI::newLine();
            CLI::write('--- machine-readable output ---', 'yellow');
            CLI::write("API_KEY={$rawKey}");
            CLI::write("APP_ID={$appId}");
            CLI::write('--- end ---', 'yellow');
        }

        CLI::newLine();
        CLI::write("Next: as superadmin, open the IAM Roles UI to assign per-resource permissions for the '{$code}' app.", 'cyan');

        return EXIT_SUCCESS;
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function ensureApplication(BaseConnection $db, string $code, string $name, string $now): int
    {
        $result   = $db->table('applications')->where('code', $code)->get();
        $existing = $result === false ? null : $result->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function ensurePermission(BaseConnection $db, int $appId, string $code, string $now): int
    {
        $result   = $db->table('permissions')
            ->where('application_id', $appId)
            ->where('code', "{$code}.access")
            ->get();
        $existing = $result === false ? null : $result->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $db->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => "{$code}.access",
            'resource'       => $code,
            'action'         => 'access',
            'description'    => "Baseline access to the {$code} application",
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $db->insertID();
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function resolveUserRoleId(BaseConnection $db): ?int
    {
        $result = $db->table('roles')->where('code', 'user')->get();
        $row    = $result === false ? null : $result->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function ensureRolePermission(BaseConnection $db, int $roleId, int $permissionId): void
    {
        $exists = $db->table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->countAllResults() > 0;

        if (! $exists) {
            $db->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * @param BaseConnection<object, object> $db
     * @return array{id:int,key_prefix:string}|null
     */
    private function findActiveApiKeyForApplication(BaseConnection $db, int $appId): ?array
    {
        $result = $db->table('api_keys')
            ->select('id, key_prefix')
            ->where('application_id', $appId)
            ->where('is_active', 1)
            ->limit(1)
            ->get();
        $row    = $result === false ? null : $result->getRowArray();

        return $row !== null ? ['id' => (int) $row['id'], 'key_prefix' => (string) $row['key_prefix']] : null;
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function createApiKey(BaseConnection $db, int $appId, string $name, string $now): string
    {
        $material = new ApiKeyMaterialService();
        $rawKey   = $material->generateRawKey();
        $hash     = $material->hash($rawKey);

        $ok = $db->table('api_keys')->insert([
            'application_id'      => $appId,
            'name'                => $name,
            'key_prefix'          => substr($rawKey, 0, 12),
            'key_hash'            => $hash,
            'is_active'           => 1,
            'rate_limit_requests' => 600,
            'rate_limit_window'   => 60,
            'user_rate_limit'     => 60,
            'ip_rate_limit'       => 200,
            'created_at'          => $now,
        ]);

        if ($ok === false) {
            throw new \RuntimeException(sprintf(
                'Failed to insert api_key for application_id=%d: %s',
                $appId,
                json_encode($db->error())
            ));
        }

        return $rawKey;
    }
}
