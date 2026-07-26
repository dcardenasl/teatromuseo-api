<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\Response\Auth\ServiceTokenResponseDTO;
use App\Entities\ApiKeyEntity;
use App\Entities\ApplicationEntity;
use App\Interfaces\Auth\ServiceTokenServiceInterface;
use App\Interfaces\Iam\ApplicationPermissionsResolverInterface;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\ApplicationModel;
use App\Services\Tokens\Support\ApiKeyMaterialService;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Issues OAuth client_credentials-style JWTs for domain applications.
 *
 * The caller authenticates via X-App-Key (verified by the AppKeyRequiredFilter
 * upstream). This service re-resolves the key by hash, loads its bound
 * application, fetches the application's permissions, and mints a short-lived
 * JWT with `sub: service:<code>` and `scope` set to those permission codes.
 */
class ServiceTokenService implements ServiceTokenServiceInterface
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository,
        private readonly ApiKeyMaterialService $apiKeyMaterial,
        private readonly ApplicationModel $applicationModel,
        private readonly ApplicationPermissionsResolverInterface $applicationPermissionsResolver,
        private readonly JwtServiceInterface $jwtService,
        private readonly int $serviceTokenTtl,
    ) {
    }

    public function issue(string $rawAppKey): ServiceTokenResponseDTO
    {
        if ($rawAppKey === '') {
            throw new AuthorizationException(lang('Auth.appKeyMissing'));
        }

        $apiKey = $this->apiKeyRepository->findByHash($this->apiKeyMaterial->hash($rawAppKey));
        if (! $apiKey instanceof ApiKeyEntity || ! $apiKey->isActive()) {
            // The filter rejects inactive/missing keys, but defend in depth in
            // case the cache and DB drift between filter and service.
            throw new AuthorizationException(lang('Auth.appKeyInvalid'));
        }

        $applicationId = $apiKey->application_id;
        if (! is_int($applicationId) || $applicationId <= 0) {
            throw new AuthorizationException(lang('Iam.apiKeyHasNoApplication'));
        }

        /** @var ApplicationEntity|null $application */
        $application = $this->applicationModel->find($applicationId);
        if ($application === null) {
            throw new NotFoundException(lang('Iam.applicationNotFound'));
        }

        $code = isset($application->code) ? (string) $application->code : '';
        if ($code === '') {
            throw new NotFoundException(lang('Iam.applicationNotFound'));
        }

        $permissions = $this->applicationPermissionsResolver->resolve($applicationId);

        $accessToken = $this->jwtService->encodeServiceToken(
            "service:{$code}",
            $permissions,
            $this->serviceTokenTtl,
        );

        return new ServiceTokenResponseDTO(
            access_token: $accessToken,
            token_type: 'Bearer',
            expires_in: $this->serviceTokenTtl,
            scope: $permissions,
        );
    }

    public function issueByKeyId(int $appKeyId): ServiceTokenResponseDTO
    {
        /** @var ApiKeyEntity|null $apiKey */
        $apiKey = $this->apiKeyRepository->find($appKeyId);
        if (! $apiKey instanceof ApiKeyEntity || ! $apiKey->isActive()) {
            throw new AuthorizationException(lang('Auth.appKeyInvalid'));
        }

        $applicationId = $apiKey->application_id;
        if (! is_int($applicationId) || $applicationId <= 0) {
            throw new AuthorizationException(lang('Iam.apiKeyHasNoApplication'));
        }

        /** @var ApplicationEntity|null $application */
        $application = $this->applicationModel->find($applicationId);
        if ($application === null) {
            throw new NotFoundException(lang('Iam.applicationNotFound'));
        }

        $code = isset($application->code) ? (string) $application->code : '';
        if ($code === '') {
            throw new NotFoundException(lang('Iam.applicationNotFound'));
        }

        $permissions = $this->applicationPermissionsResolver->resolve($applicationId);

        $accessToken = $this->jwtService->encodeServiceToken(
            "service:{$code}",
            $permissions,
            $this->serviceTokenTtl,
        );

        return new ServiceTokenResponseDTO(
            access_token: $accessToken,
            token_type: 'Bearer',
            expires_in: $this->serviceTokenTtl,
            scope: $permissions,
        );
    }
}
