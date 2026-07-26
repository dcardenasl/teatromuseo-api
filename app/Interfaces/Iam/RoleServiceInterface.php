<?php

declare(strict_types=1);

namespace App\Interfaces\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use App\DTO\Response\Iam\PermissionResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\CrudServiceContract;

interface RoleServiceInterface extends CrudServiceContract
{
    /** @return PermissionResponseDTO[] */
    public function listPermissions(int $roleId, ?SecurityContext $context = null);

    /** @return PermissionResponseDTO[] */
    public function attachPermissions(int $roleId, AttachPermissionsRequestDTO $request, ?SecurityContext $context = null);

    public function detachPermission(int $roleId, int $permissionId, ?SecurityContext $context = null): bool;
}
