<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\DTO\Request\Iam\ListUserPermissionsRequestDTO;
use App\Services\Iam\UserPermissionsService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class UserPermissionsController extends ApiController
{
    protected UserPermissionsService $userPermissionsService;

    protected function resolveDefaultService(): object
    {
        $this->userPermissionsService = Services::userPermissionsService();

        return $this->userPermissionsService;
    }

    public function index(int $userId): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->userPermissionsService->listForUser($userId, $dto, $context),
            ListUserPermissionsRequestDTO::class
        );
    }
}
