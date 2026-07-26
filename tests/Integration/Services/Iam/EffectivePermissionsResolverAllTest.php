<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Iam;

use App\Services\Iam\EffectivePermissionsResolver;
use Config\Services;
use Tests\Support\ApiTestCase;

/**
 * Integration tests for EffectivePermissionsResolver::resolveAll().
 *
 * Exercises cross-application permission aggregation, caching, cache
 * invalidation, superadmin path, and per-user isolation.
 */
class EffectivePermissionsResolverAllTest extends ApiTestCase
{
    private EffectivePermissionsResolver $resolver;
    private \CodeIgniter\Database\ConnectionInterface $testDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDb = \Config\Database::connect();
        $this->resolver = new EffectivePermissionsResolver(
            $this->testDb,
            Services::cache()
        );
    }

    public function testResolveAllAggregatesPermissionsFromMultipleApps(): void
    {
        $appA  = $this->insertApp('multi-a');
        $appB  = $this->insertApp('multi-b');
        $role  = $this->insertRole('multi-role-agg');
        $user  = $this->insertUser();

        $permA = $this->insertPerm($appA, 'multi-a.read');
        $permB = $this->insertPerm($appB, 'multi-b.read');
        $this->attachPermToRole($role, $permA);
        $this->attachPermToRole($role, $permB);
        $this->assignRoleToUser($user, $role);

        $codes = $this->resolver->resolveAll($user);

        $this->assertContains('multi-a.read', $codes);
        $this->assertContains('multi-b.read', $codes);
    }

    public function testResolveAllReturnsSortedAndDeduplicatedCodes(): void
    {
        $appA  = $this->insertApp('dedup-app-a');
        $appB  = $this->insertApp('dedup-app-b');
        $roleA = $this->insertRole('dedup-role-a');
        $roleB = $this->insertRole('dedup-role-b');
        $user  = $this->insertUser();

        $pA = $this->insertPerm($appA, 'dedup-app-a.z-perm');
        $pB = $this->insertPerm($appA, 'dedup-app-a.a-perm');
        $pC = $this->insertPerm($appB, 'dedup-app-b.m-perm');

        $this->attachPermToRole($roleA, $pA);
        $this->attachPermToRole($roleA, $pB);
        $this->attachPermToRole($roleB, $pB);  // duplicate across roles
        $this->attachPermToRole($roleB, $pC);

        $this->assignRoleToUser($user, $roleA);
        $this->assignRoleToUser($user, $roleB);

        $codes = $this->resolver->resolveAll($user);

        $this->assertSame(array_values($codes), $codes, 'Must be a list (no string keys)');
        $this->assertSame($codes, array_values(array_unique($codes)), 'Must be deduplicated');
        $sorted = $codes;
        sort($sorted);
        $this->assertSame($sorted, $codes, 'Must be sorted ascending');
        $this->assertCount(3, $codes);
    }

    public function testResolveAllReturnsEmptyForUserWithNoRoles(): void
    {
        $user = $this->insertUser();

        $this->assertSame([], $this->resolver->resolveAll($user));
    }

    public function testResolveAllSuperadminGetsAllPermissionsAcrossApps(): void
    {
        $appA  = $this->insertApp('super-app-a');
        $appB  = $this->insertApp('super-app-b');
        $this->insertPerm($appA, 'super-app-a.read');
        $this->insertPerm($appB, 'super-app-b.read');

        $superRole = $this->testDb->table('roles')->where('code', 'superadmin')->get()->getRowArray();
        $this->assertNotNull($superRole, 'superadmin role must exist (run RbacBootstrapSeeder)');

        $user = $this->insertUser();
        $this->assignRoleToUser($user, (int) $superRole['id']);

        $codes = $this->resolver->resolveAll($user);

        $this->assertContains('super-app-a.read', $codes);
        $this->assertContains('super-app-b.read', $codes);
    }

    public function testResolveAllCachesResult(): void
    {
        $app  = $this->insertApp('cache-all-app');
        $role = $this->insertRole('cache-all-role');
        $user = $this->insertUser();

        $p1 = $this->insertPerm($app, 'cache-all-app.first');
        $this->attachPermToRole($role, $p1);
        $this->assignRoleToUser($user, $role);

        $first = $this->resolver->resolveAll($user);
        $this->assertSame(['cache-all-app.first'], $first);

        // Add another permission while cache is warm
        $p2 = $this->insertPerm($app, 'cache-all-app.second');
        $this->attachPermToRole($role, $p2);

        $second = $this->resolver->resolveAll($user);
        $this->assertSame(['cache-all-app.first'], $second, 'Cache must shield the second call');

        // After invalidation the new permission must appear
        $this->resolver->invalidateAll();
        $third = $this->resolver->resolveAll($user);
        $this->assertContains('cache-all-app.second', $third);
    }

    public function testInvalidateForUserClearsAllCache(): void
    {
        $app  = $this->insertApp('inv-user-app');
        $role = $this->insertRole('inv-user-role');
        $user = $this->insertUser();

        $p = $this->insertPerm($app, 'inv-user-app.read');
        $this->attachPermToRole($role, $p);
        $this->assignRoleToUser($user, $role);

        // Prime the all-cache
        $before = $this->resolver->resolveAll($user);
        $this->assertSame(['inv-user-app.read'], $before);

        // Add another permission while all-cache is warm
        $p2 = $this->insertPerm($app, 'inv-user-app.write');
        $this->attachPermToRole($role, $p2);

        // invalidateForUser must bust the all-cache as well
        $this->resolver->invalidateForUser($user, 1);

        $after = $this->resolver->resolveAll($user);
        $this->assertContains('inv-user-app.write', $after, 'invalidateForUser() must clear the all-cache');
    }

    public function testResolveAllIsolatesAcrossUsers(): void
    {
        $app   = $this->insertApp('isolate-app');
        $roleA = $this->insertRole('isolate-role-a');
        $roleB = $this->insertRole('isolate-role-b');
        $userA = $this->insertUser();
        $userB = $this->insertUser();

        $pA = $this->insertPerm($app, 'isolate-app.alpha');
        $pB = $this->insertPerm($app, 'isolate-app.beta');

        $this->attachPermToRole($roleA, $pA);
        $this->attachPermToRole($roleB, $pB);
        $this->assignRoleToUser($userA, $roleA);
        $this->assignRoleToUser($userB, $roleB);

        $codesA = $this->resolver->resolveAll($userA);
        $codesB = $this->resolver->resolveAll($userB);

        $this->assertContains('isolate-app.alpha', $codesA);
        $this->assertNotContains('isolate-app.beta', $codesA);
        $this->assertContains('isolate-app.beta', $codesB);
        $this->assertNotContains('isolate-app.alpha', $codesB);
    }

    private function insertApp(string $code): int
    {
        $this->testDb->table('applications')->insert([
            'code'       => $code,
            'name'       => ucfirst($code),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function insertPerm(int $appId, string $code): int
    {
        [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

        $this->testDb->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => "Test permission {$code}",
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function insertRole(string $code): int
    {
        $existing = $this->testDb->table('roles')->where('code', $code)->get()->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->testDb->table('roles')->insert([
            'code'               => $code,
            'name'               => $code,
            'description'        => "Test role {$code}",
            'is_system'          => 0,
            'is_self_assignable' => 0,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function insertUser(): int
    {
        $email = 'resolveall_' . uniqid() . '@test.example';

        $this->testDb->table('users')->insert([
            'email'             => $email,
            'password'          => password_hash('Test1234!', PASSWORD_BCRYPT),
            'first_name'        => 'Test',
            'last_name'         => 'User',
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->testDb->insertID();
    }

    private function attachPermToRole(int $roleId, int $permId): void
    {
        $exists = $this->testDb->table('role_permissions')
            ->where('role_id', $roleId)->where('permission_id', $permId)
            ->countAllResults() > 0;

        if (! $exists) {
            $this->testDb->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    private function assignRoleToUser(int $userId, int $roleId): void
    {
        $exists = $this->testDb->table('user_roles')
            ->where('user_id', $userId)->where('role_id', $roleId)
            ->countAllResults() > 0;

        if (! $exists) {
            $this->testDb->table('user_roles')->insert([
                'user_id'             => $userId,
                'role_id'             => $roleId,
                'assigned_at'         => date('Y-m-d H:i:s'),
                'assigned_by_user_id' => null,
            ]);
        }
    }
}
