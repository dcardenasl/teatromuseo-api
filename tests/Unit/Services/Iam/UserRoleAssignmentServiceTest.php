<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\UserRoleModel;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Iam\UserRoleAssignmentService;
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
}
