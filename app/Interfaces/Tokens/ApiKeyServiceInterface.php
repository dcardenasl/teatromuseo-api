<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * API Key Service Interface
 *
 * Defines the contract for managing API keys with stratified rate limiting.
 */
interface ApiKeyServiceInterface
{
    /**
     * List all API keys with pagination and filtering
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Get a single API key by ID
     */
    public function show(int $id, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Create a new API key
     */
    public function store(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Update an existing API key
     */
    public function update(int $id, \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Delete an API key
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
