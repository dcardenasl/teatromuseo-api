<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\Response\ApiKeys\ApiKeyResponseDTO;
use App\Entities\ApiKeyEntity;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Interfaces\Tokens\ApiKeyServiceInterface;
use App\Services\Tokens\Actions\CreateApiKeyAction;
use App\Services\Tokens\Actions\UpdateApiKeyAction;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * @extends BaseCrudService<ApiKeyEntity>
 */
class ApiKeyService extends BaseCrudService implements ApiKeyServiceInterface
{
    public function __construct(
        ApiKeyRepositoryInterface $apiKeyRepository,
        ResponseMapperInterface $responseMapper,
        protected CreateApiKeyAction $createApiKeyAction,
        protected UpdateApiKeyAction $updateApiKeyAction
    ) {
        parent::__construct($apiKeyRepository, $responseMapper);
    }

    /**
     * Create a new API key
     */
    public function store(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($request) {
            ['entity' => $apiKey, 'key' => $rawKey] = $this->createApiKeyAction->execute($request);

            $apiKeyData = $apiKey->toArray();
            $apiKeyData['key'] = $rawKey;

            return ApiKeyResponseDTO::fromArray($apiKeyData);
        });
    }

    /**
     * Update an API key
     */
    public function update(int $id, \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($id, $request) {
            $updatedApiKey = $this->updateApiKeyAction->execute($id, $request);
            return $this->mapToResponse($updatedApiKey);
        });
    }
}
