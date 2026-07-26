<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * Verifies the hierarchical guardrails enforced by the redesigned IAM:
 *  - IAM CRUD (roles, permissions, applications) is gated by `iam.superadmin-access`.
 *  - SuperAdmin users are invisible/untouchable for non-SuperAdmin actors.
 *  - Self-edit is blocked for everyone (admin and superadmin alike).
 *  - Default user role is assigned automatically on creation/registration.
 *
 * @internal
 */
final class PrivilegeEscalationTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testAdminCannotInjectSuperadminPermissionIntoExistingRole(): void
    {
        $this->actAs('admin');
        $adminRoleId        = $this->roleIdByCode('admin');
        $superadminPermId   = $this->permissionIdByCode('iam.superadmin-access');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/roles/{$adminRoleId}/permissions/attach",
            ['permission_ids' => [(string) $superadminPermId]]
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotUpdateSystemRole(): void
    {
        $this->actAs('admin');
        $superadminRoleId = $this->roleIdByCode('superadmin');

        $result = $this->withBodyFormat('json')->put(
            "/api/v1/iam/roles/{$superadminRoleId}",
            ['name' => 'Hijacked']
        );

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteSystemRole(): void
    {
        $this->actAs('admin');
        $superadminRoleId = $this->roleIdByCode('superadmin');

        $result = $this->delete("/api/v1/iam/roles/{$superadminRoleId}");

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteSuperadminUser(): void
    {
        $saUserId = $this->createUser('victim-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        // The framework may either return a 403/404 HTTP response or surface the
        // AuthorizationException directly to the test runner. Both indicate the
        // operation was blocked. The invariant we care about is that the user
        // survives.
        try {
            $this->delete("/api/v1/users/{$saUserId}");
        } catch (\dcardenasl\Ci4ApiCore\Exceptions\ApiException) {
            // Expected blocked path.
        }

        $stillExists = \Config\Database::connect()
            ->table('users')->where('id', $saUserId)->countAllResults() > 0;
        $this->assertTrue($stillExists, 'Superadmin user should not have been deleted by an admin actor.');
    }

    public function testAdminCannotApproveSuperadminUser(): void
    {
        $saUserId = $this->createUser('pending-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin', 'pending_approval');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        try {
            $this->withBodyFormat('json')->post("/api/v1/users/{$saUserId}/approve");
        } catch (\dcardenasl\Ci4ApiCore\Exceptions\ApiException) {
            // Expected blocked path.
        }

        $row = \Config\Database::connect()
            ->table('users')->where('id', $saUserId)->select('status')->get()?->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('pending_approval', (string) $row['status']);
    }

    public function testAdminCannotUpdateOwnUser(): void
    {
        $this->actAs('admin');
        $ownId = (int) $this->currentUserId;

        $result = $this->withBodyFormat('json')->put(
            "/api/v1/users/{$ownId}",
            ['first_name' => 'Hacky']
        );

        $result->assertStatus(403);
    }

    public function testAdminListingHidesSuperadminUsers(): void
    {
        $saUserId = $this->createUser('hidden-sa-' . uniqid() . '@example.com', 'ValidPass123!', 'superadmin');
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($saUserId, 1);

        $this->actAs('admin');

        $result = $this->get('/api/v1/users?per_page=100');
        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true) ?? [];
        $items = $body['data']['data'] ?? ($body['data'] ?? ($body['items'] ?? []));
        $ids   = [];
        if (is_array($items)) {
            foreach ($items as $u) {
                if (is_array($u) && isset($u['id'])) {
                    $ids[] = (int) $u['id'];
                }
            }
        }

        $this->assertNotContains($saUserId, $ids, 'Admin listing should not enumerate SuperAdmin users.');
    }

    public function testAdminCannotReadIamRoles(): void
    {
        $this->actAs('admin');

        $result = $this->get('/api/v1/iam/roles');

        $result->assertStatus(403);
    }

    public function testNewUserGetsDefaultUserRole(): void
    {
        $this->actAs('admin');

        $result = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email'      => 'auto-role-' . uniqid() . '@example.com',
            'first_name' => 'Auto',
            'last_name'  => 'Role',
        ]);

        $result->assertStatus(201);

        $body          = json_decode($result->getJSON(), true) ?? [];
        $createdUserId = (int) ($body['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdUserId);

        $db = \Config\Database::connect();
        $row = $db->table('user_roles ur')
            ->select('r.code')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $createdUserId)
            ->get()
            ?->getRowArray();

        $this->assertNotNull($row, 'Newly created user should have at least one role assigned.');
        $this->assertSame('user', (string) $row['code'], 'Newly created user must default to the "user" role.');
    }

    public function testPublicRegistrationAutoAssignsUserRole(): void
    {
        $this->clearTestRequestHeaders();

        $email = 'public-signup-' . uniqid() . '@example.com';
        $this->withBodyFormat('json')->post('/api/v1/auth/register', [
            'email'      => $email,
            'password'   => 'ValidPass123!Strong',
            'first_name' => 'Pub',
            'last_name'  => 'Signup',
        ]);

        $db   = \Config\Database::connect();
        $user = $db->table('users')->where('email', $email)->get()?->getRowArray();
        $this->assertNotNull($user, 'Registered user should exist after /auth/register.');

        $row = $db->table('user_roles ur')
            ->select('r.code')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', (int) $user['id'])
            ->get()
            ?->getRowArray();

        $this->assertNotNull($row, 'Public-registered user must be auto-assigned a role.');
        $this->assertSame('user', (string) $row['code'], 'Public-registered user must default to the "user" role.');
    }

    public function testSuperAdminCanAttachSuperadminPermissionToAnyRole(): void
    {
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'code'               => 'qa-test-role-' . uniqid(),
            'name'               => 'QA Test Role',
            'description'        => 'Created by feature test',
            'is_system'          => 0,
            'is_self_assignable' => 0,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
        $newRoleId = (int) $db->insertID();

        $this->actAs('superadmin');
        $superadminPermId = $this->permissionIdByCode('iam.superadmin-access');

        $result = $this->withBodyFormat('json')->post(
            "/api/v1/iam/roles/{$newRoleId}/permissions/attach",
            ['permission_ids' => [(string) $superadminPermId]]
        );

        $result->assertStatus(200);
    }

    private function roleIdByCode(string $code): int
    {
        $db  = \Config\Database::connect();
        $row = $db->table('roles')->where('code', $code)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function permissionIdByCode(string $code): int
    {
        $db  = \Config\Database::connect();
        $row = $db->table('permissions')->where('code', $code)->where('application_id', 1)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }
}
