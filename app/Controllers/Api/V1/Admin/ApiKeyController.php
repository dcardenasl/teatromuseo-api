<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyIndexRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\Interfaces\Tokens\ApiKeyServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Modernized API Key Controller
 *
 * Admin-only CRUD endpoints for managing API keys with stratified rate limiting.
 * Uses automated DTO validation.
 */
class ApiKeyController extends ApiController
{
    protected ApiKeyServiceInterface $apiKeyService;

    protected function resolveDefaultService(): object
    {
        $this->apiKeyService = Services::apiKeyService();

        return $this->apiKeyService;
    }

    /**
     * List all API keys
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ApiKeyIndexRequestDTO::class);
    }

    /**
     * Get a single API key
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->apiKeyService->show($id, $context)
        );
    }

    /**
     * Create a new API key
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', ApiKeyCreateRequestDTO::class);
    }

    /**
     * Update an API key
     */
    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->apiKeyService->update($id, $dto, $context),
            ApiKeyUpdateRequestDTO::class
        );
    }

    /**
     * Delete an API key
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->apiKeyService->destroy($id, $context)
        );
    }
}
