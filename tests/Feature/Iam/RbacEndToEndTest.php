<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use App\DTO\Request\Iam\PermissionIndexRequestDTO;
use App\Libraries\Iam\SelfPermissionService;
use App\Services\Iam\EffectivePermissionsResolver;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * @internal
 */
final class RbacEndToEndTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testSuperadminResolvesAllPermissionsForRequestedApplication(): void
    {
        $actor = $this->actAs('superadmin');
        $appId = $this->insertApplication('cms-wildcard', 'CMS Wildcard');
        $this->insertPermission($appId, 'cms-wildcard.widget.read', 'widget', 'read');
        $this->insertPermission($appId, 'cms-wildcard.widget.create', 'widget', 'create');

        $resolver = new EffectivePermissionsResolver(
            model(\App\Models\UserRoleModel::class),
            model(\App\Models\PermissionModel::class),
            Services::cache()
        );

        $this->assertSame(
            ['cms-wildcard.widget.create', 'cms-wildcard.widget.read'],
            $resolver->resolve((int) $actor['user_id'], $appId)
        );
    }

    public function testSelfPermissionSyncAttachesCreatedAndExistingPermissionsToSuperadmin(): void
    {
        $appId = $this->insertApplication('cms-sync', 'CMS Sync');
        $existingId = $this->insertPermission($appId, 'cms-sync.page.read', 'page', 'read');

        $service = new SelfPermissionService(
            model(\App\Models\PermissionModel::class),
            model(\App\Models\ApplicationModel::class),
        );

        $result = $service->sync($appId, [
            ['code' => 'cms-sync.page.read', 'resource' => 'page', 'action' => 'read', 'description' => 'Read pages'],
            ['code' => 'cms-sync.page.create', 'resource' => 'page', 'action' => 'create', 'description' => 'Create pages'],
        ]);

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->existing);

        $superadminRoleId = $this->roleId('superadmin');
        $createdId = $this->permissionId($appId, 'cms-sync.page.create');

        $this->assertRoleHasPermission($superadminRoleId, $existingId);
        $this->assertRoleHasPermission($superadminRoleId, $createdId);
    }

    public function testRbacSeederKeepsSuperadminDomainPermissions(): void
    {
        $appId = $this->insertApplication('cms-seed', 'CMS Seed');
        $permissionId = $this->insertPermission($appId, 'cms-seed.article.read', 'article', 'read');
        $superadminRoleId = $this->roleId('superadmin');
        $this->attachRolePermission($superadminRoleId, $permissionId);

        $this->seed(\App\Database\Seeds\RbacBootstrapSeeder::class);

        $this->assertRoleHasPermission($superadminRoleId, $permissionId);
    }

    public function testRbacSeederKeepsFileAdminPermissionOutOfSelfAssignableUserRole(): void
    {
        $db = \Config\Database::connect();
        $app = $db->table('applications')->where('code', 'self')->get()?->getRowArray();
        $this->assertNotNull($app);

        $filesAdminId = $this->permissionId((int) $app['id'], 'files.admin');
        $this->assertGreaterThan(0, $filesAdminId);
        $this->assertRoleHasPermission($this->roleId('admin'), $filesAdminId);

        $userHasAdmin = $db->table('role_permissions')
            ->where('role_id', $this->roleId('user'))
            ->where('permission_id', $filesAdminId)
            ->countAllResults();
        $this->assertSame(0, $userHasAdmin);
    }

    public function testPermissionIndexAcceptsPerPageUpToFiveHundred(): void
    {
        $dto = new PermissionIndexRequestDTO(['per_page' => 500], Services::validation());

        $this->assertSame(500, $dto->per_page);
    }

    public function testRolePermissionMatrixReturnsApplicationsRolesAndAssignments(): void
    {
        $this->actAs('superadmin');
        $appId = $this->insertApplication('cms-matrix', 'CMS Matrix');
        $permissionId = $this->insertPermission($appId, 'cms-matrix.block.read', 'block', 'read');
        $roleId = $this->insertRole('editor', 'Editor');
        $this->attachRolePermission($roleId, $permissionId);

        $result = $this->get('/api/v1/iam/role-permission-matrix');

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $data = $json['data'] ?? [];

        $this->assertContains('cms', array_column($data['applications'] ?? [], 'code'));
        $this->assertContains('editor', array_column($data['roles'] ?? [], 'code'));
        $this->assertSame([$permissionId], $data['assignments'][(string) $roleId] ?? []);
    }

    private function insertApplication(string $code, string $name): int
    {
        $db = \Config\Database::connect();
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function insertPermission(int $appId, string $code, string $resource, string $action): int
    {
        $db = \Config\Database::connect();
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

    private function insertRole(string $code, string $name): int
    {
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'code'        => $code,
            'name'        => $name,
            'description' => '',
            'is_system'   => 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function roleId(string $code): int
    {
        $row = \Config\Database::connect()->table('roles')->where('code', $code)->get()?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function permissionId(int $appId, string $code): int
    {
        $row = \Config\Database::connect()->table('permissions')
            ->where('application_id', $appId)
            ->where('code', $code)
            ->get()
            ?->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function attachRolePermission(int $roleId, int $permissionId): void
    {
        \Config\Database::connect()->table('role_permissions')->insert([
            'role_id'       => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    private function assertRoleHasPermission(int $roleId, int $permissionId): void
    {
        $count = \Config\Database::connect()->table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->countAllResults();

        $this->assertSame(1, $count);
    }
}
