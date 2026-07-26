<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\DTO\Request\Iam\PermissionCreateRequestDTO;
use App\DTO\Request\Iam\PermissionIndexRequestDTO;
use App\DTO\Request\Iam\PermissionUpdateRequestDTO;
use App\Interfaces\Iam\PermissionServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class PermissionController extends ApiController
{
    protected PermissionServiceInterface $permissionService;

    protected function resolveDefaultService(): object
    {
        $this->permissionService = Services::permissionService();

        return $this->permissionService;
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', PermissionIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', PermissionCreateRequestDTO::class);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->permissionService->update($id, $dto, $context),
            PermissionUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->permissionService->show($id, $context));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->permissionService->destroy($id, $context));
    }
}
