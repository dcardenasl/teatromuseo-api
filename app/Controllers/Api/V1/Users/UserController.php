<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Users;

use App\DTO\Request\Users\UserCreateRequestDTO;
use App\DTO\Request\Users\UserIndexRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\Interfaces\Users\UserServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * Modernized User Controller
 *
 * Handles administrative and profile-related user operations with strict DTOs.
 */
class UserController extends ApiController
{
    protected UserServiceInterface $userService;

    protected function resolveDefaultService(): object
    {
        $this->userService = Services::userService();

        return $this->userService;
    }

    /**
     * List users with filters and pagination
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', UserIndexRequestDTO::class);
    }

    /**
     * Display a specific user
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->userService->show($id, $context));
    }

    /**
     * Create a new user (Admin only)
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', UserCreateRequestDTO::class);
    }

    /**
     * Update an existing user
     */
    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->userService->update($id, $dto, $context),
            UserUpdateRequestDTO::class
        );
    }

    /**
     * Approve a pending user
     */
    public function approve(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function ($dto, $context) use ($id) {
                $clientBaseUrl = $this->request->getVar('client_base_url');
                $locale = $this->request->getVar('locale');
                return $this->userService->approve(
                    $id,
                    $context,
                    is_string($clientBaseUrl) ? $clientBaseUrl : null,
                    is_string($locale) ? $locale : null
                );
            }
        );
    }

    /**
     * Delete a user
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->userService->destroy($id, $context));
    }

    /**
     * Lists the global roles the current actor is allowed to assign to other
     * users. Anti-escalation: a role appears only if all its permissions are
     * a subset of the actor's effective permissions in the current application.
     */
    public function assignableRoles(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            $perms = $context !== null ? $context->permissions : [];
            $assignable = Services::assignableRolesService()->listAssignable($perms);

            return $this->response->setJSON(ApiResponse::success($assignable));
        });
    }
}
