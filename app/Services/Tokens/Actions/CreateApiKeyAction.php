<?php

declare(strict_types=1);

namespace App\Services\Tokens\Actions;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Services\Tokens\Support\ApiKeyMaterialService;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class CreateApiKeyAction
{
    public function __construct(
        protected ApiKeyRepositoryInterface $apiKeyRepository,
        protected ApiKeyMaterialService $apiKeyMaterialService
    ) {
    }

    /**
     * @return array{entity: \App\Entities\ApiKeyEntity, key: string}
     */
    public function execute(ApiKeyCreateRequestDTO $request)
    {
        $rawKey = $this->apiKeyMaterialService->generateRawKey();
        $hash = $this->apiKeyMaterialService->hash($rawKey);

        $data = [
            'name' => $request->name,
            'key_prefix' => substr($rawKey, 0, 12),
            'key_hash' => $hash,
            'is_active' => 1,
            'rate_limit_requests' => $request->rate_limit_requests ?? 600,
            'rate_limit_window' => $request->rate_limit_window ?? 60,
            'user_rate_limit' => $request->user_rate_limit ?? 60,
            'ip_rate_limit' => $request->ip_rate_limit ?? 200,
        ];

        $id = $this->apiKeyRepository->insert($data);

        if ($id === false || $id === 0 || $id === '' || $id === true) {
            throw new ValidationException(lang('Api.validationFailed'), $this->apiKeyRepository->errors());
        }

        /** @var \App\Entities\ApiKeyEntity|null $apiKey */
        $apiKey = $this->apiKeyRepository->find($id);
        if ($apiKey === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['apiKey' => lang('Api.resourceNotFound')]);
        }

        return [
            'entity' => $apiKey,
            'key' => $rawKey,
        ];
    }
}
