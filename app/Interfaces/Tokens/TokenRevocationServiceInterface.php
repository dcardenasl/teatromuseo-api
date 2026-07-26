<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

use App\DTO\Request\Identity\RevokeAccessTokenRequestDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Token Revocation Service Interface
 *
 * Contract for token revocation functionality
 */
interface TokenRevocationServiceInterface
{
    /**
     * Revoke an access token by adding its JTI to blacklist
     */
    public function revokeToken(string $jti, int $expiresAt, ?SecurityContext $context = null): bool;

    /**
     * Revoke an access token from authorization header
     */
    public function revokeAccessToken(RevokeAccessTokenRequestDTO $request, ?SecurityContext $context = null): bool;

    /**
     * Check if a token is revoked
     */
    public function isRevoked(string $jti): bool;

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): bool;

    /**
     * Clean up expired blacklisted tokens
     */
    public function cleanupExpired(): int;
}
