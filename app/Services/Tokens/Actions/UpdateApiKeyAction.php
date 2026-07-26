<?php

declare(strict_types=1);

namespace App\Services\Tokens\Actions;

use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class UpdateApiKeyAction
{
    public function __construct(
        protected ApiKeyRepositoryInterface $apiKeyRepository
    ) {
    }

    public function execute(int $id, ApiKeyUpdateRequestDTO $request): \App\Entities\ApiKeyEntity
    {
        /** @var \App\Entities\ApiKeyEntity|null $existing */
        $existing = $this->apiKeyRepository->find($id);
        if ($existing === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $data = array_filter([
            'name' => $request->name,
            'is_active' => $request->is_active,
            'rate_limit_requests' => $request->rate_limit_requests,
            'rate_limit_window' => $request->rate_limit_window,
            'user_rate_limit' => $request->user_rate_limit,
            'ip_rate_limit' => $request->ip_rate_limit,
        ], fn ($value) => $value !== null);

        if ($data === []) {
            throw new BadRequestException(lang('Api.noFieldsToUpdate'));
        }

        if (!$this->apiKeyRepository->update($id, $data)) {
            throw new ValidationException(lang('Api.validationFailed'), $this->apiKeyRepository->errors());
        }

        /** @var \App\Entities\ApiKeyEntity|null $updated */
        $updated = $this->apiKeyRepository->find($id);
        if ($updated === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $updated;
    }
}
