<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * Modernized Auth Token Service Interface
 */
interface AuthTokenServiceInterface
{
    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(RefreshTokenRequestDTO $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Revoke current access token from authorization header.
     */
    public function revokeToken(string $authorizationHeader, ?SecurityContext $context = null): OperationResult;

    /**
     * Revoke all tokens for current user.
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): OperationResult;
}
