<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\Services\Iam\RolePermissionMatrixService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class RolePermissionMatrixController extends ApiController
{
    protected RolePermissionMatrixService $rolePermissionMatrixService;

    protected function resolveDefaultService(): object
    {
        $this->rolePermissionMatrixService = Services::rolePermissionMatrixService();

        return $this->rolePermissionMatrixService;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest(fn (): array => $this->rolePermissionMatrixService->matrix()->toArray());
    }
}
