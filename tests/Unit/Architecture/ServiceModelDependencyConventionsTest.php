<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail against Services bypassing the Model layer with raw query
 * builder / connection access.
 *
 * Rewritten 2026-08-06 (LAYER-03, saneamiento arquitectónico). This test
 * used to forbid `use App\Models\...;` in app/Services, with six
 * "justified exceptions" whitelisted (Auth/PasswordResetService,
 * Auth/ServiceTokenService, Auth/UserInvitationService, System/MetricsService,
 * Tokens/RefreshTokenService, Tokens/TokenRevocationService).
 *
 * That rule pointed at the wrong axis: services importing and using a Model
 * IS the sanctioned pattern in this app (see the six pre-existing exceptions
 * above, and now the eight IAM services migrated off raw builder access —
 * UserRoleAssignmentService, RoleService, EffectivePermissionsResolver,
 * RolePermissionAssignmentService, RolePermissionMatrixService,
 * IamAuthorizationService, AssignableRolesService,
 * ApplicationPermissionsResolver — plus UserPermissionsService). What
 * actually indicates a layer violation is a Service reaching past its Model
 * entirely, straight to `$db->table(...)` / `Database::connect(...)`. That
 * is the real guardrail: zero tolerance, no whitelist. Extend a Model with a
 * new finder/mutator instead of dropping to raw SQL in a Service. If a raw
 * query is truly unavoidable (e.g. a transaction-scoped lock with no Active
 * Record equivalent), add a narrowly-scoped, commented exception at the call
 * site itself — not a blanket whitelist entry here.
 */
class ServiceModelDependencyConventionsTest extends CIUnitTestCase
{
    public function testServicesDoNotBypassModelsWithRawBuilderAccess(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $serviceDir = $root . DIRECTORY_SEPARATOR . 'app/Services';

        $allowed = [];
        sort($allowed);

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($serviceDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            // Strip comments and string literals so a mention inside a
            // docblock (like this one) can't trigger a false positive.
            $code = '';
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }

            $bypassesModels = preg_match('/->\s*table\s*\(/', $code) === 1
                || preg_match('/\\\\?Database\s*::\s*connect\s*\(/', $code) === 1;

            if (!$bypassesModels) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));
            $found[] = $relative;
        }

        sort($found);
        $this->assertSame(
            $allowed,
            $found,
            "Services with raw query-builder/DB access changed.\n" .
            'Extend the relevant Model with a finder/mutator instead of using $db->table()/Database::connect() ' .
            'directly. Update this whitelist only for a narrowly-scoped, justified exception.'
        );
    }
}
