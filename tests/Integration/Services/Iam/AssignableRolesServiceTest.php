<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Iam;

use App\Services\Iam\AssignableRolesService;
use Tests\Support\ApiTestCase;

/**
 * AssignableRolesService Integration Tests
 *
 * Audit B7.1 (2026-05-06): pins the anti-escalation contract that used
 * to live in `UserController::assignableRoles` (raw queries + filtering
 * inside the controller). The rule:
 *
 *   A role is assignable iff every permission code attached to that role
 *   is already in the actor's effective permission set.
 *
 * Verified against the real `roles` / `role_permissions` / `permissions`
 * tables to catch both the SQL queries and the array_diff semantics.
 *
 * @internal
 */
final class AssignableRolesServiceTest extends ApiTestCase
{
    private AssignableRolesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Wipe any seeded roles/role_permissions so each test starts from
        // a controlled fixture set. Permissions/applications keep their
        // seeded shape — we add new ones as needed.
        //
        // emptyTable() (DELETE FROM) instead of truncate() because MySQL
        // forbids TRUNCATE on tables referenced by FK constraints; the
        // child tables are cleared first so the parent DELETE succeeds.
        $db = \Config\Database::connect();
        $db->table('role_permissions')->emptyTable();
        $db->table('user_roles')->emptyTable();
        $db->table('roles')->emptyTable();

        $this->service = new AssignableRolesService($db);
    }

    public function testReturnsEmptyArrayWhenNoRolesExist(): void
    {
        $this->assertSame([], $this->service->listAssignable(['users.read']));
    }

    public function testRoleWithNoPermissionsIsAlwaysAssignable(): void
    {
        $roleId = $this->insertRole('viewer', 'Viewer', 'Read-only role');

        $assignable = $this->service->listAssignable([]);

        $this->assertCount(1, $assignable);
        $this->assertSame($roleId, $assignable[0]->id);
        $this->assertSame('viewer', $assignable[0]->code);
        $this->assertSame('Viewer', $assignable[0]->name);
        $this->assertSame('Read-only role', $assignable[0]->description);
    }

    public function testActorWithFullPermissionSetCanAssignAllRoles(): void
    {
        $appId = $this->insertApp('test-app');
        $readPermId = $this->insertPerm($appId, 'users.read');
        $writePermId = $this->insertPerm($appId, 'users.write');

        $editorId = $this->insertRole('editor', 'Editor');
        $viewerId = $this->insertRole('viewer', 'Viewer');

        $this->attachPerm($editorId, $readPermId);
        $this->attachPerm($editorId, $writePermId);
        $this->attachPerm($viewerId, $readPermId);

        $assignable = $this->service->listAssignable(['users.read', 'users.write']);

        $codes = array_map(fn ($role) => $role->code, $assignable);
        sort($codes);
        $this->assertSame(['editor', 'viewer'], $codes);
    }

    public function testRoleIsFilteredOutWhenItHasPermissionsActorLacks(): void
    {
        $appId = $this->insertApp('test-app');
        $readPermId = $this->insertPerm($appId, 'users.read');
        $writePermId = $this->insertPerm($appId, 'users.write');

        $editorId = $this->insertRole('editor', 'Editor');
        $viewerId = $this->insertRole('viewer', 'Viewer');

        $this->attachPerm($editorId, $readPermId);
        $this->attachPerm($editorId, $writePermId);   // editor needs write
        $this->attachPerm($viewerId, $readPermId);

        // Actor only has read — must NOT see editor (escalation).
        $assignable = $this->service->listAssignable(['users.read']);

        $codes = array_map(fn ($role) => $role->code, $assignable);
        $this->assertSame(['viewer'], $codes, 'Editor must be filtered: assigning it would grant users.write to target.');
        $this->assertNotContains('editor', $codes);
    }

    public function testActorWithEmptyPermissionsCanOnlyAssignZeroPermRoles(): void
    {
        $appId = $this->insertApp('test-app');
        $readPermId = $this->insertPerm($appId, 'users.read');

        $emptyRoleId = $this->insertRole('empty', 'Empty');
        $viewerId = $this->insertRole('viewer', 'Viewer');
        $this->attachPerm($viewerId, $readPermId);

        $assignable = $this->service->listAssignable([]);

        $this->assertCount(1, $assignable);
        $this->assertSame($emptyRoleId, $assignable[0]->id);
        $this->assertSame('empty', $assignable[0]->code);
    }

    public function testResultsAreOrderedByNameAscending(): void
    {
        $this->insertRole('zeta', 'Zeta');
        $this->insertRole('alpha', 'Alpha');
        $this->insertRole('mu', 'Mu');

        $assignable = $this->service->listAssignable([]);

        $names = array_map(fn ($role) => $role->name, $assignable);
        $this->assertSame(['Alpha', 'Mu', 'Zeta'], $names);
    }

    public function testReturnedShapeContainsAllExpectedKeys(): void
    {
        $this->insertRole('admin', 'Admin', 'System admin', isSystem: 1, isSelfAssignable: 0);

        $assignable = $this->service->listAssignable([]);

        $this->assertCount(1, $assignable);
        $role = $assignable[0];

        $this->assertIsInt($role->id);
        $this->assertSame('admin', $role->code);
        $this->assertSame('System admin', $role->description);
        $this->assertTrue($role->is_system);
    }

    // ===================== fixtures =====================

    private function insertApp(string $code): int
    {
        $db = \Config\Database::connect();
        $query = $db->table('applications')->where('code', $code)->get();
        $existing = $query !== false ? $query->getRowArray() : null;
        if (is_array($existing) && isset($existing['id'])) {
            return (int) $existing['id'];
        }
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => ucfirst($code),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function insertPerm(int $appId, string $code): int
    {
        $db = \Config\Database::connect();

        // Idempotent: the seeder + previous tests may have already created the
        // (application_id, code) row, and the unique index on those columns
        // would reject a duplicate insert.
        $query = $db->table('permissions')
            ->where('application_id', $appId)
            ->where('code', $code)
            ->get();
        $existing = $query !== false ? $query->getRowArray() : null;
        if (is_array($existing) && isset($existing['id'])) {
            return (int) $existing['id'];
        }

        [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

        $db->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => "Test permission {$code}",
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function insertRole(
        string $code,
        string $name,
        ?string $description = null,
        int $isSystem = 0,
        int $isSelfAssignable = 0
    ): int {
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'code'               => $code,
            'name'               => $name,
            'description'        => $description,
            'is_system'          => $isSystem,
            'is_self_assignable' => $isSelfAssignable,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function attachPerm(int $roleId, int $permId): void
    {
        // role_permissions is a pure join table — no timestamp columns.
        \Config\Database::connect()->table('role_permissions')->insert([
            'role_id'       => $roleId,
            'permission_id' => $permId,
        ]);
    }
}
