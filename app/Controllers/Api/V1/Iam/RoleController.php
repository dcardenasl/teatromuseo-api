<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use App\DTO\Request\Iam\RoleCreateRequestDTO;
use App\DTO\Request\Iam\RoleIndexRequestDTO;
use App\DTO\Request\Iam\RoleUpdateRequestDTO;
use App\Interfaces\Iam\RoleServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class RoleController extends ApiController
{
    protected RoleServiceInterface $roleService;

    protected function resolveDefaultService(): object
    {
        $this->roleService = Services::roleService();

        return $this->roleService;
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', RoleIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', RoleCreateRequestDTO::class);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->roleService->update($id, $dto, $context),
            RoleUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->roleService->show($id, $context));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->roleService->destroy($id, $context));
    }

    public function listPermissions(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->roleService->listPermissions($id, $context));
    }

    public function attachPermissions(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->roleService->attachPermissions($id, $dto, $context),
            AttachPermissionsRequestDTO::class
        );
    }

    public function detachPermission(int $id, int $permissionId): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->roleService->detachPermission($id, $permissionId, $context));
    }
}
