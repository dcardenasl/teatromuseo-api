<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * Verifies that Role create/update accept `permission_ids[]` and apply it
 * atomically via RolePermissionAssignmentService::syncPermissions, mirroring
 * how UserUpdateRequestDTO + UserRoleAssignmentService handle role_ids[].
 *
 * @internal
 */
final class RoleSyncPermissionsTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testCreateRoleWithPermissionIdsAttachesThemAtomically(): void
    {
        $this->actAs('superadmin');

        [$permA, $permB] = $this->createTestPermissions(2);

        $code = 'qa-create-' . uniqid();
        $result = $this->withBodyFormat('json')->post('/api/v1/iam/roles', [
            'code'           => $code,
            'name'           => 'QA Create',
            'description'    => '',
            'permission_ids' => [(string) $permA, (string) $permB],
        ]);

        $result->assertStatus(201);

        $body   = json_decode($result->getJSON(), true) ?? [];
        $roleId = (int) ($body['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $roleId);

        $this->assertEqualsCanonicalizing(
            [$permA, $permB],
            $this->rolePermissionIds($roleId)
        );
    }

    public function testUpdateRoleReplacesPermissionSetAtomically(): void
    {
        $this->actAs('superadmin');

        [$permA, $permB, $permC] = $this->createTestPermissions(3);
        $roleId = $this->createTestRole([$permA, $permB]);

        // Replace: drop B, keep A, add C.
        $result = $this->withBodyFormat('json')->put("/api/v1/iam/roles/{$roleId}", [
            'permission_ids' => [(string) $permA, (string) $permC],
        ]);

        $result->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$permA, $permC],
            $this->rolePermissionIds($roleId)
        );
    }

    public function testUpdateWithEmptyPermissionIdsRemovesAll(): void
    {
        $this->actAs('superadmin');

        [$permA, $permB] = $this->createTestPermissions(2);
        $roleId = $this->createTestRole([$permA, $permB]);

        $result = $this->withBodyFormat('json')->put("/api/v1/iam/roles/{$roleId}", [
            'permission_ids' => [],
        ]);

        $result->assertStatus(200);

        $this->assertSame([], $this->rolePermissionIds($roleId));
    }

    public function testUpdateWithoutPermissionIdsLeavesPermissionsUntouched(): void
    {
        $this->actAs('superadmin');

        [$permA, $permB] = $this->createTestPermissions(2);
        $roleId = $this->createTestRole([$permA, $permB]);

        $result = $this->withBodyFormat('json')->put("/api/v1/iam/roles/{$roleId}", [
            'name' => 'Renamed',
        ]);

        $result->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [$permA, $permB],
            $this->rolePermissionIds($roleId)
        );
    }

    public function testNonSuperadminCannotGrantPermissionTheyDoNotOwn(): void
    {
        $this->actAs('admin');
        $superadminPermId = $this->permissionIdByCode('iam.superadmin-access');

        $code = 'qa-escalate-' . uniqid();
        $result = $this->withBodyFormat('json')->post('/api/v1/iam/roles', [
            'code'           => $code,
            'name'           => 'QA Escalate',
            'description'    => '',
            'permission_ids' => [(string) $superadminPermId],
        ]);

        // The role row may or may not be created (the throw happens after the
        // store transaction commits if the inner sync runs in a nested wrap).
        // What matters is: the dangerous permission is NOT attached anywhere.
        $this->assertNotEquals(201, $result->getStatusCode(), 'Admin must not be allowed to grant superadmin permission.');

        $db   = \Config\Database::connect();
        $rows = $db->table('roles r')
            ->select('rp.permission_id')
            ->join('role_permissions rp', 'rp.role_id = r.id')
            ->where('r.code', $code)
            ->where('rp.permission_id', $superadminPermId)
            ->get();
        $this->assertSame([], $rows === false ? [] : $rows->getResultArray());
    }

    private function getSelfApplicationId(): int
    {
        $db = \Config\Database::connect();
        $row = $db->table('applications')->where('code', 'self')->get()->getRowArray();
        return $row ? (int) $row['id'] : 1;
    }

    /**
     * @return list<int>
     */
    private function createTestPermissions(int $count): array
    {
        $db  = \Config\Database::connect();
        $appId = $this->getSelfApplicationId();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $code = 'qa.test.' . uniqid('', true);
            $db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => 'qa',
                'action'         => 'test-' . $i,
                'description'    => 'qa test permission',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $permissionId = (int) $db->insertID();

            // Grant the permission to admin so anti-escalation lets the test
            // actor (superadmin) freely sync it. Superadmin already bypasses
            // anti-escalation; this is a defensive belt-and-suspenders.
            $ids[] = $permissionId;
        }

        return $ids;
    }

    /**
     * @param list<int> $permissionIds
     */
    private function createTestRole(array $permissionIds): int
    {
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'code'        => 'qa-role-' . uniqid(),
            'name'        => 'QA Role',
            'description' => 'created by RoleSyncPermissionsTest',
            'is_system'   => 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $roleId = (int) $db->insertID();

        foreach ($permissionIds as $pid) {
            $db->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $pid,
            ]);
        }

        return $roleId;
    }

    /**
     * @return list<int>
     */
    private function rolePermissionIds(int $roleId): array
    {
        $db  = \Config\Database::connect();
        $rs  = $db->table('role_permissions')->where('role_id', $roleId)->select('permission_id')->get();
        $rows = $rs === false ? [] : $rs->getResultArray();

        return array_values(array_map(static fn (array $r) => (int) $r['permission_id'], $rows));
    }

    private function permissionIdByCode(string $code): int
    {
        $db  = \Config\Database::connect();
        $appId = $this->getSelfApplicationId();
        $row = $db->table('permissions')
            ->where('code', $code)
            ->where('application_id', $appId)
            ->get()
            ?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }
}
