<?php

declare(strict_types=1);

namespace Config;

trait TokenSecurityServices
{
    public static function jwtService(bool $getShared = true): \App\Interfaces\Tokens\JwtServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('jwtService');
        }

        $apiConfig = config('Api');
        $secretKey = $apiConfig->jwtSecretKey;
        $ttl = $apiConfig->jwtAccessTokenTtl;
        $issuer = (string) env('app.baseURL', '');
        if (! $issuer) {
            throw new \LogicException(
                'Missing app.baseURL in .env. '
                . 'This is used as the JWT token issuer claim. '
                . 'Example: app.baseURL=http://localhost:8080'
            );
        }

        return new \App\Services\Tokens\JwtService(
            $secretKey,
            $ttl,
            $issuer
        );
    }

    public static function refreshTokenService(bool $getShared = true): \App\Interfaces\Tokens\RefreshTokenServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('refreshTokenService');
        }

        $apiConfig = config('Api');
        $refreshTokenTtl = $apiConfig->jwtRefreshTokenTtl;
        $accessTokenTtl = $apiConfig->jwtAccessTokenTtl;

        return new \App\Services\Tokens\RefreshTokenService(
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::userModel(),
            static::userAccountGuard(),
            static::effectivePermissionsResolver(),
            $refreshTokenTtl,
            $accessTokenTtl
        );
    }

    public static function tokenRevocationService(bool $getShared = true): \App\Interfaces\Tokens\TokenRevocationServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('tokenRevocationService');
        }

        $apiConfig = config('Api');
        return new \App\Services\Tokens\TokenRevocationService(
            new \App\Models\TokenBlacklistModel(),
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::auditService(),
            static::cache(),
            static::bearerTokenService(),
            $apiConfig->jwtAccessTokenTtl,
            $apiConfig->jwtRevocationCacheTtl
        );
    }

    public static function bearerTokenService(bool $getShared = true): \App\Services\Tokens\BearerTokenService
    {
        if ($getShared) {
            return static::getSharedInstance('bearerTokenService');
        }

        return new \App\Services\Tokens\BearerTokenService();
    }

    public static function apiKeyService(bool $getShared = true): \App\Interfaces\Tokens\ApiKeyServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyService');
        }

        return new \App\Services\Tokens\ApiKeyService(
            static::apiKeyRepository(),
            static::apiKeyResponseMapper(),
            static::createApiKeyAction(),
            static::updateApiKeyAction()
        );
    }

    public static function apiKeyResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyResponseMapper');
        }

        return new \dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper(
            \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class
        );
    }

    public static function apiKeyMaterialService(bool $getShared = true): \App\Services\Tokens\Support\ApiKeyMaterialService
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyMaterialService');
        }

        return new \App\Services\Tokens\Support\ApiKeyMaterialService();
    }

    public static function createApiKeyAction(bool $getShared = true): \App\Services\Tokens\Actions\CreateApiKeyAction
    {
        if ($getShared) {
            return static::getSharedInstance('createApiKeyAction');
        }

        return new \App\Services\Tokens\Actions\CreateApiKeyAction(
            static::apiKeyRepository(),
            static::apiKeyMaterialService()
        );
    }

    public static function updateApiKeyAction(bool $getShared = true): \App\Services\Tokens\Actions\UpdateApiKeyAction
    {
        if ($getShared) {
            return static::getSharedInstance('updateApiKeyAction');
        }

        return new \App\Services\Tokens\Actions\UpdateApiKeyAction(
            static::apiKeyRepository()
        );
    }

    public static function authTokenService(bool $getShared = true): \App\Interfaces\Tokens\AuthTokenServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('authTokenService');
        }

        return new \App\Services\Tokens\AuthTokenService(
            static::refreshTokenService(),
            static::tokenRevocationService(),
            static::requestDtoFactory()
        );
    }

    public static function tokenIntrospectionService(bool $getShared = true): \App\Interfaces\Auth\TokenIntrospectionServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('tokenIntrospectionService');
        }

        return new \App\Services\Auth\TokenIntrospectionService(
            static::jwtService(),
            static::tokenRevocationService(),
            static::effectivePermissionsResolver()
        );
    }

    public static function serviceTokenService(bool $getShared = true): \App\Interfaces\Auth\ServiceTokenServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('serviceTokenService');
        }

        $apiConfig = config('Api');

        return new \App\Services\Auth\ServiceTokenService(
            static::apiKeyRepository(),
            static::apiKeyMaterialService(),
            model(\App\Models\ApplicationModel::class),
            static::applicationPermissionsResolver(),
            static::jwtService(),
            (int) $apiConfig->jwtServiceTokenTtl
        );
    }
}
