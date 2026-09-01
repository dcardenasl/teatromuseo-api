<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use Tests\Support\IntegrationTestCase;

/**
 * RolePermissionModel Integration Tests
 */
class RolePermissionModelTest extends IntegrationTestCase
{
    protected $seed = \App\Database\Seeds\RbacBootstrapSeeder::class;

    protected RolePermissionModel $model;
    protected RoleModel $roleModel;
    protected PermissionModel $permissionModel;

    protected int $roleId;
    protected int $permissionAId;
    protected int $permissionBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new RolePermissionModel();
        $this->roleModel = new RoleModel();
        $this->permissionModel = new PermissionModel();

        $this->roleId = (int) $this->roleModel->insert([
            'code' => 'rp-role-' . uniqid('', true),
            'name' => 'RP Role',
        ]);
        $this->permissionAId = (int) $this->permissionModel->insert([
            'application_id' => 1,
            'code' => 'rp.permission.a.' . uniqid('', true),
            'resource' => 'rp',
            'action' => 'a',
        ]);
        $this->permissionBId = (int) $this->permissionModel->insert([
            'application_id' => 1,
            'code' => 'rp.permission.b.' . uniqid('', true),
            'resource' => 'rp',
            'action' => 'b',
        ]);
    }

    public function testInsertPairsAttachesPermissionsToRole(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId, $this->permissionBId]);

        $ids = $this->model->getPermissionIdsForRole($this->roleId);
        sort($ids);
        $expected = [$this->permissionAId, $this->permissionBId];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testInsertPairsWithEmptyArrayIsNoop(): void
    {
        $this->model->insertPairs($this->roleId, []);

        $this->assertSame([], $this->model->getPermissionIdsForRole($this->roleId));
    }

    public function testDeletePairsForRoleWithSpecificIdsRemovesOnlyThose(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId, $this->permissionBId]);

        $this->model->deletePairsForRole($this->roleId, [$this->permissionAId]);

        $this->assertSame([$this->permissionBId], $this->model->getPermissionIdsForRole($this->roleId));
    }

    public function testDeletePairsForRoleWithNullRemovesAll(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId, $this->permissionBId]);

        $this->model->deletePairsForRole($this->roleId, null);

        $this->assertSame([], $this->model->getPermissionIdsForRole($this->roleId));
    }

    public function testDeletePairsForRoleWithEmptyArrayIsNoop(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId]);

        $this->model->deletePairsForRole($this->roleId, []);

        $this->assertSame([$this->permissionAId], $this->model->getPermissionIdsForRole($this->roleId));
    }

    public function testDeletePairRemovesOnlyThatSpecificPair(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId, $this->permissionBId]);

        $this->model->deletePair($this->roleId, $this->permissionAId);

        $this->assertSame([$this->permissionBId], $this->model->getPermissionIdsForRole($this->roleId));
    }

    public function testGetPermissionCodesByRoleIdsReturnsCodesKeyedByRole(): void
    {
        $this->model->insertPairs($this->roleId, [$this->permissionAId, $this->permissionBId]);
        $permissionA = $this->permissionModel->find($this->permissionAId);
        $permissionB = $this->permissionModel->find($this->permissionBId);

        $byRole = $this->model->getPermissionCodesByRoleIds([$this->roleId]);

        $this->assertArrayHasKey($this->roleId, $byRole);
        sort($byRole[$this->roleId]);
        $expected = [$permissionA->code, $permissionB->code];
        sort($expected);
        $this->assertSame($expected, $byRole[$this->roleId]);
    }

    public function testGetPermissionCodesByRoleIdsWithEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], $this->model->getPermissionCodesByRoleIds([]));
    }
}
