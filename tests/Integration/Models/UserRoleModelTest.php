<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserModel;
use App\Models\UserRoleModel;
use Tests\Support\IntegrationTestCase;

/**
 * UserRoleModel Integration Tests
 */
class UserRoleModelTest extends IntegrationTestCase
{
    protected $seed = \App\Database\Seeds\RbacBootstrapSeeder::class;

    protected UserRoleModel $model;
    protected UserModel $userModel;
    protected RoleModel $roleModel;
    protected PermissionModel $permissionModel;
    protected RolePermissionModel $rolePermissionModel;

    protected int $userId;
    protected int $roleAId;
    protected int $roleBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new UserRoleModel();
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
        $this->permissionModel = new PermissionModel();
        $this->rolePermissionModel = new RolePermissionModel();

        $this->userId = (int) $this->userModel->insert([
            'email' => 'userrole-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
        ]);

        // Roles are global (no application_id column — dropped in migration
        // 2026-05-03-100006); only permissions remain scoped per-application.
        $this->roleAId = (int) $this->roleModel->insert([
            'code' => 'role-a-' . uniqid('', true),
            'name' => 'Role A',
        ]);
        $this->roleBId = (int) $this->roleModel->insert([
            'code' => 'role-b-' . uniqid('', true),
            'name' => 'Role B',
        ]);
    }

    public function testAssignCreatesPair(): void
    {
        $this->assertFalse($this->model->pairExists($this->userId, $this->roleAId));

        $this->model->assign($this->userId, $this->roleAId, null);

        $this->assertTrue($this->model->pairExists($this->userId, $this->roleAId));
    }

    public function testAssignManyCreatesMultiplePairs(): void
    {
        $this->model->assignMany($this->userId, [$this->roleAId, $this->roleBId], 99);

        $this->assertTrue($this->model->pairExists($this->userId, $this->roleAId));
        $this->assertTrue($this->model->pairExists($this->userId, $this->roleBId));
    }

    public function testAssignManyWithEmptyArrayIsNoop(): void
    {
        $this->model->assignMany($this->userId, [], null);

        $this->assertSame([], $this->model->getRoleIdsForUser($this->userId));
    }

    public function testRemoveDeletesPair(): void
    {
        $this->model->assign($this->userId, $this->roleAId);

        $this->model->remove($this->userId, $this->roleAId);

        $this->assertFalse($this->model->pairExists($this->userId, $this->roleAId));
    }

    public function testRemoveManyDeletesGivenPairsOnly(): void
    {
        $this->model->assignMany($this->userId, [$this->roleAId, $this->roleBId]);

        $this->model->removeMany($this->userId, [$this->roleAId]);

        $this->assertFalse($this->model->pairExists($this->userId, $this->roleAId));
        $this->assertTrue($this->model->pairExists($this->userId, $this->roleBId));
    }

    public function testRemoveManyWithEmptyArrayIsNoop(): void
    {
        $this->model->assignMany($this->userId, [$this->roleAId]);

        $this->model->removeMany($this->userId, []);

        $this->assertTrue($this->model->pairExists($this->userId, $this->roleAId));
    }

    public function testGetRoleIdsForUserReturnsAssignedIds(): void
    {
        $this->model->assignMany($this->userId, [$this->roleAId, $this->roleBId]);

        $ids = $this->model->getRoleIdsForUser($this->userId);

        sort($ids);
        $expected = [$this->roleAId, $this->roleBId];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testGetRolesForUserReturnsJoinedRoleDetails(): void
    {
        $this->model->assign($this->userId, $this->roleAId);

        $roles = $this->model->getRolesForUser($this->userId);

        $this->assertCount(1, $roles);
        $this->assertSame($this->roleAId, $roles[0]['id']);
        $this->assertArrayHasKey('code', $roles[0]);
        $this->assertArrayHasKey('is_system', $roles[0]);
    }

    public function testUserHasPermissionCodeAndRoleCode(): void
    {
        $permissionId = (int) $this->permissionModel->insert([
            'application_id' => 1,
            'code' => 'custom.permission.' . uniqid('', true),
            'resource' => 'custom',
            'action' => 'do',
        ]);
        $this->rolePermissionModel->insertPairs($this->roleAId, [$permissionId]);
        $this->model->assign($this->userId, $this->roleAId);

        $role = $this->roleModel->find($this->roleAId);
        $permission = $this->permissionModel->find($permissionId);

        $this->assertTrue($this->model->userHasPermissionCode($this->userId, $permission->code));
        $this->assertFalse($this->model->userHasPermissionCode($this->userId, 'nonexistent.code'));

        $this->assertTrue($this->model->userHasRoleCode($this->userId, $role->code));
        $this->assertFalse($this->model->userHasRoleCode($this->userId, 'nonexistent-role'));
    }

    public function testGetPermissionCodesForUserReturnsDeduplicatedCodes(): void
    {
        $permissionId = (int) $this->permissionModel->insert([
            'application_id' => 1,
            'code' => 'dedup.permission.' . uniqid('', true),
            'resource' => 'dedup',
            'action' => 'do',
        ]);
        // Attach the same permission through two different roles the user holds.
        $this->rolePermissionModel->insertPairs($this->roleAId, [$permissionId]);
        $this->rolePermissionModel->insertPairs($this->roleBId, [$permissionId]);
        $this->model->assignMany($this->userId, [$this->roleAId, $this->roleBId]);

        $permission = $this->permissionModel->find($permissionId);
        $codes = $this->model->getPermissionCodesForUser($this->userId);

        $this->assertContains($permission->code, $codes);
        $this->assertSame(array_unique($codes), array_values($codes));
    }

    public function testGetPermissionCodesForUserAndApplicationFiltersByApplication(): void
    {
        $permissionId = (int) $this->permissionModel->insert([
            'application_id' => 1,
            'code' => 'scoped.permission.' . uniqid('', true),
            'resource' => 'scoped',
            'action' => 'do',
        ]);
        $this->rolePermissionModel->insertPairs($this->roleAId, [$permissionId]);
        $this->model->assign($this->userId, $this->roleAId);

        $permission = $this->permissionModel->find($permissionId);

        $codesForApp1 = $this->model->getPermissionCodesForUserAndApplication($this->userId, 1);
        $this->assertContains($permission->code, $codesForApp1);

        $codesForOtherApp = $this->model->getPermissionCodesForUserAndApplication($this->userId, 999999);
        $this->assertNotContains($permission->code, $codesForOtherApp);
    }
}
