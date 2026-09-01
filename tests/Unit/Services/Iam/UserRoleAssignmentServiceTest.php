<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserRoleModel;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Iam\UserRoleAssignmentService;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

final class UserRoleAssignmentServiceTest extends TestCase
{
    public function testCmsProfileSelectionAlwaysIncludesTheBaseUserRole(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->expects($this->exactly(2))
            ->method('getRoleIdsForUser')
            ->willReturnOnConsecutiveCalls([], [20, 1]);
        $userRoles->expects($this->once())
            ->method('assignMany')
            ->with(42, [20, 1], null);
        $roles = $this->createMock(RoleModel::class);
        $roles->expects($this->once())
            ->method('findCodesByIds')
            ->with([20])
            ->willReturn([20 => 'cms-editor']);
        $roles->expects($this->once())
            ->method('findIdByCode')
            ->with('user')
            ->willReturn(1);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->syncRoles(42, [20]);
    }

    public function testNonCmsProfilesAreNotGivenTheBaseUserRoleImplicitly(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->expects($this->exactly(2))
            ->method('getRoleIdsForUser')
            ->willReturnOnConsecutiveCalls([], [30]);
        $userRoles->expects($this->once())
            ->method('assignMany')
            ->with(42, [30], null);

        $roles = $this->createMock(RoleModel::class);
        $roles->expects($this->once())
            ->method('findCodesByIds')
            ->with([30])
            ->willReturn([30 => 'catalog-editor']);
        $roles->expects($this->never())->method('findIdByCode');

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->syncRoles(42, [30]);
    }

    // ==================== assignRole() TESTS ====================

    public function testAssignRoleIsNoopWhenPairAlreadyExists(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('pairExists')->with(1, 5)->willReturn(true);
        $userRoles->expects($this->never())->method('assign');

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->never())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $this->createMock(RoleModel::class),
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->assignRole(1, 5);
    }

    public function testAssignRoleInsertsAndInvalidatesCacheWhenNew(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('pairExists')->willReturn(false);
        $userRoles->expects($this->once())->method('assign')->with(1, 5, 9);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $this->createMock(RoleModel::class),
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->assignRole(1, 5, 9);
    }

    // ==================== assignRoleByCode() TESTS ====================

    public function testAssignRoleByCodeResolvesCodeToId(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('pairExists')->willReturn(false);
        $userRoles->expects($this->once())->method('assign')->with(1, 7, null);

        $roles = $this->createMock(RoleModel::class);
        $roles->method('findIdByCode')->with('admin')->willReturn(7);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->assignRoleByCode(1, 'admin');
    }

    public function testAssignRoleByCodeThrowsWhenCodeUnknown(): void
    {
        $roles = $this->createMock(RoleModel::class);
        $roles->method('findIdByCode')->willReturn(null);

        $service = new UserRoleAssignmentService(
            $this->createMock(UserRoleModel::class),
            $roles,
            $this->createMock(RolePermissionModel::class),
            $this->createMock(EffectivePermissionsResolver::class)
        );

        $this->expectException(NotFoundException::class);

        $service->assignRoleByCode(1, 'nonexistent');
    }

    // ==================== removeRole() TESTS ====================

    public function testRemoveRoleDoesNotReassignDefaultWhenRolesRemain(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->expects($this->once())->method('remove')->with(1, 5);
        $userRoles->method('getRoleIdsForUser')->willReturn([9]);
        $userRoles->expects($this->never())->method('assign');

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $this->createMock(RoleModel::class),
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->removeRole(1, 5);
    }

    public function testRemoveRoleReassignsDefaultUserRoleWhenLeftWithNone(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->expects($this->once())->method('remove')->with(1, 5);
        $userRoles->method('getRoleIdsForUser')->willReturn([]);
        $userRoles->method('pairExists')->willReturn(false);
        $userRoles->expects($this->once())->method('assign')->with(1, 3, null);

        $roles = $this->createMock(RoleModel::class);
        $roles->method('findIdByCode')->with('user')->willReturn(3);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->removeRole(1, 5);
    }

    // ==================== getUserRoles() / isSuperadmin() TESTS ====================

    public function testGetUserRolesDelegatesToModel(): void
    {
        $expected = [['id' => 1, 'code' => 'admin', 'name' => 'Admin', 'description' => null, 'is_system' => 1]];
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('getRolesForUser')->with(1)->willReturn($expected);

        $service = new UserRoleAssignmentService(
            $userRoles,
            $this->createMock(RoleModel::class),
            $this->createMock(RolePermissionModel::class),
            $this->createMock(EffectivePermissionsResolver::class)
        );

        $this->assertSame($expected, $service->getUserRoles(1));
    }

    public function testIsSuperadminDelegatesToModel(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('userHasPermissionCode')->with(1, 'iam.superadmin-access')->willReturn(true);

        $service = new UserRoleAssignmentService(
            $userRoles,
            $this->createMock(RoleModel::class),
            $this->createMock(RolePermissionModel::class),
            $this->createMock(EffectivePermissionsResolver::class)
        );

        $this->assertTrue($service->isSuperadmin(1));
    }

    // ==================== syncRoles() removal + anti-escalation TESTS ====================

    public function testSyncRolesRemovesRolesNoLongerPresent(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('getRoleIdsForUser')->willReturnOnConsecutiveCalls([10, 11], [10]);
        $userRoles->expects($this->never())->method('assignMany');
        $userRoles->expects($this->once())->method('removeMany')->with(1, [11]);

        $roles = $this->createMock(RoleModel::class);
        $roles->method('findCodesByIds')->willReturn([10 => 'catalog-editor']);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $this->createMock(RolePermissionModel::class),
            $resolver
        );

        $service->syncRoles(1, [10]);
    }

    public function testSyncRolesAllowsActorToGrantRolesTheyFullyOwn(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        // First call is the pre-diff "current roles" read; second is the
        // post-write "did we leave the user with zero roles?" guard.
        $userRoles->method('getRoleIdsForUser')->willReturnOnConsecutiveCalls([], [5]);
        $userRoles->method('getPermissionCodesForUser')->with(2)->willReturn(['users.read', 'users.write']);
        $userRoles->expects($this->once())->method('assignMany')->with(1, [5], 2);
        $userRoles->expects($this->never())->method('removeMany');
        $userRoles->expects($this->never())->method('assign');

        $roles = $this->createMock(RoleModel::class);
        $roles->method('findCodesByIds')->willReturn([5 => 'catalog-editor']);
        $roles->expects($this->never())->method('findIdByCode');

        $rolePermissions = $this->createMock(RolePermissionModel::class);
        $rolePermissions->method('getPermissionCodesByRoleIds')->with([5])->willReturn([5 => ['users.read']]);

        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())->method('invalidateAll');

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $rolePermissions,
            $resolver
        );

        $service->syncRoles(1, [5], 2);
    }

    public function testSyncRolesThrowsWhenActorGrantsRoleWithUnownedPermissions(): void
    {
        $userRoles = $this->createMock(UserRoleModel::class);
        $userRoles->method('getPermissionCodesForUser')->with(2)->willReturn(['users.read']);

        $roles = $this->createMock(RoleModel::class);
        $roles->method('findCodesByIds')->willReturn([5 => 'catalog-editor']);

        $rolePermissions = $this->createMock(RolePermissionModel::class);
        $rolePermissions->method('getPermissionCodesByRoleIds')->with([5])->willReturn([5 => ['users.read', 'users.write']]);

        $service = new UserRoleAssignmentService(
            $userRoles,
            $roles,
            $rolePermissions,
            $this->createMock(EffectivePermissionsResolver::class)
        );

        $this->expectException(AuthorizationException::class);

        $service->syncRoles(1, [5], 2);
    }
}
