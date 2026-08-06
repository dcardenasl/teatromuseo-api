<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RolePermissionMatrixResponseDTO;
use App\Models\ApplicationModel;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;

final class RolePermissionMatrixService
{
    public function __construct(
        private readonly ApplicationModel $applicationModel,
        private readonly PermissionModel $permissionModel,
        private readonly RoleModel $roleModel,
        private readonly RolePermissionModel $rolePermissionModel
    ) {
    }

    public function matrix(): RolePermissionMatrixResponseDTO
    {
        $applications = $this->loadApplications();
        $roles = $this->roleModel->listAllOrderedByCode();
        $assignments = $this->rolePermissionModel->allAssignmentsGroupedByRole();

        return new RolePermissionMatrixResponseDTO($applications, $roles, $assignments);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadApplications(): array
    {
        $appRows = $this->applicationModel->listAllOrderedByCode();
        $byApplication = $this->permissionModel->groupedByApplication();

        return array_values(array_map(static fn (array $app): array => [
            'id'          => $app['id'],
            'code'        => $app['code'],
            'name'        => $app['name'],
            'permissions' => $byApplication[$app['id']] ?? [],
        ], $appRows));
    }
}
