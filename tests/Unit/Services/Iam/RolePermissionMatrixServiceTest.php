<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\DTO\Response\Iam\RolePermissionMatrixResponseDTO;
use App\Models\ApplicationModel;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Services\Iam\RolePermissionMatrixService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Unit tests for RolePermissionMatrixService. Mocks all four Models it
 * composes so the read-model assembly (grouping permissions under their
 * owning application, wiring assignments per role) is verified without a
 * database.
 *
 * @internal
 */
final class RolePermissionMatrixServiceTest extends CIUnitTestCase
{
    public function testServiceIsResolvable(): void
    {
        $service = Services::rolePermissionMatrixService(false);

        $this->assertInstanceOf(RolePermissionMatrixService::class, $service);
    }

    public function testMatrixAssemblesApplicationsRolesAndAssignments(): void
    {
        $applicationModel = $this->createMock(ApplicationModel::class);
        $applicationModel->method('listAllOrderedByCode')->willReturn([
            ['id' => 1, 'code' => 'cms', 'name' => 'CMS'],
            ['id' => 2, 'code' => 'catalog', 'name' => 'Catalog'],
        ]);

        $permissionModel = $this->createMock(PermissionModel::class);
        $permissionModel->method('groupedByApplication')->willReturn([
            1 => [
                ['id' => 10, 'code' => 'cms.read', 'resource' => 'cms', 'action' => 'read', 'description' => ''],
            ],
            // application 2 ("catalog") intentionally has no permissions.
        ]);

        $roleModel = $this->createMock(RoleModel::class);
        $roleModel->method('listAllOrderedByCode')->willReturn([
            ['id' => 100, 'code' => 'editor', 'name' => 'Editor', 'description' => null, 'is_system' => false],
        ]);

        $rolePermissionModel = $this->createMock(RolePermissionModel::class);
        $rolePermissionModel->method('allAssignmentsGroupedByRole')->willReturn([
            100 => [10],
        ]);

        $service = new RolePermissionMatrixService(
            $applicationModel,
            $permissionModel,
            $roleModel,
            $rolePermissionModel
        );

        $result = $service->matrix();

        $this->assertInstanceOf(RolePermissionMatrixResponseDTO::class, $result);

        $this->assertCount(2, $result->applications);
        $this->assertSame('cms', $result->applications[0]['code']);
        $this->assertCount(1, $result->applications[0]['permissions']);
        $this->assertSame('cms.read', $result->applications[0]['permissions'][0]['code']);

        $this->assertSame('catalog', $result->applications[1]['code']);
        $this->assertSame([], $result->applications[1]['permissions'], 'App with no permissions must get an empty list, not be omitted');

        $this->assertSame([
            ['id' => 100, 'code' => 'editor', 'name' => 'Editor', 'description' => null, 'is_system' => false],
        ], $result->roles);

        $this->assertSame([100 => [10]], $result->assignments);
    }

    public function testMatrixHandlesEmptySystemGracefully(): void
    {
        $applicationModel = $this->createMock(ApplicationModel::class);
        $applicationModel->method('listAllOrderedByCode')->willReturn([]);

        $permissionModel = $this->createMock(PermissionModel::class);
        $permissionModel->method('groupedByApplication')->willReturn([]);

        $roleModel = $this->createMock(RoleModel::class);
        $roleModel->method('listAllOrderedByCode')->willReturn([]);

        $rolePermissionModel = $this->createMock(RolePermissionModel::class);
        $rolePermissionModel->method('allAssignmentsGroupedByRole')->willReturn([]);

        $service = new RolePermissionMatrixService(
            $applicationModel,
            $permissionModel,
            $roleModel,
            $rolePermissionModel
        );

        $result = $service->matrix();

        $this->assertSame([], $result->applications);
        $this->assertSame([], $result->roles);
        $this->assertSame([], $result->assignments);
    }
}
