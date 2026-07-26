<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\Request\Identity\RevokeAccessTokenRequestDTO;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Tokens\TokenRevocationServiceInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Support\OperationResult;
use dcardenasl\Ci4ApiCore\Support\RequestDtoFactory;

/**
 * Modernized Auth Token Service
 *
 * Facade for token management operations.
 */
class AuthTokenService implements \App\Interfaces\Tokens\AuthTokenServiceInterface
{
    public function __construct(
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected TokenRevocationServiceInterface $tokenRevocationService,
        protected RequestDtoFactory $requestDtoFactory
    ) {
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        return $this->refreshTokenService->refreshAccessToken($request);
    }

    /**
     * Revoke current access token from authorization header
     */
    public function revokeToken(string $authorizationHeader, ?SecurityContext $context = null): OperationResult
    {
        $requestDto = $this->requestDtoFactory->make(
            RevokeAccessTokenRequestDTO::class,
            ['authorization_header' => $authorizationHeader]
        );

        if (!$requestDto instanceof RevokeAccessTokenRequestDTO) {
            throw new \RuntimeException(lang('Api.invalidRequest'));
        }

        $this->tokenRevocationService->revokeAccessToken($requestDto, $context);

        return OperationResult::success(null, lang('Tokens.tokenRevokedSuccess'));
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): OperationResult
    {
        if ($userId <= 0) {
            throw new AuthenticationException(lang('Auth.authRequired'));
        }

        $this->tokenRevocationService->revokeAllUserTokens($userId, $context);

        return OperationResult::success(null, lang('Tokens.allUserTokensRevoked'));
    }
}
