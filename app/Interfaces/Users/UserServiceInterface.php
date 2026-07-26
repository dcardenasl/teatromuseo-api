<?php

declare(strict_types=1);

namespace App\Interfaces\Users;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * User Service Interface
 *
 * Defines the contract for user CRUD operations with strict typing.
 */
interface UserServiceInterface
{
    /**
     * Get all users with pagination and filtering
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Get a single user by ID
     */
    public function show(int $id, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Create a new user (Admin only)
     */
    public function store(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Update an existing user
     */
    public function update(int $id, \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Delete a user (Soft delete)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Approve a pending user
     */
    public function approve(int $id, ?SecurityContext $context = null, ?string $clientBaseUrl = null, ?string $locale = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
}
