<?php

declare(strict_types=1);

namespace App\Traits\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * HasCrudActions Trait
 *
 * Provides standardized CRUD action implementations for ApiControllers.
 * Reduces boilerplate by automating the handleRequest calls for common operations.
 */
trait HasCrudActions
{
    /**
     * Map actions to their respective Request DTO classes.
     * Override these in your controller.
     */
    protected string $indexDto = '';
    protected string $createDto = '';
    protected string $updateDto = '';

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', $this->indexDto ?: null);
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->defaultService->show($id, $context));
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', $this->createDto ?: null);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->defaultService->update($id, $dto, $context),
            $this->updateDto ?: null
        );
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->defaultService->destroy($id, $context));
    }
}
