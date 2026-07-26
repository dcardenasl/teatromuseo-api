<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\Request\Identity\RevokeAccessTokenRequestDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use CodeIgniter\Cache\CacheInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * Token Revocation Service
 *
 * Handles revocation of access tokens (JWT) and refresh tokens.
 * Orchestrates blacklist database and performance cache.
 */
readonly class TokenRevocationService implements \App\Interfaces\Tokens\TokenRevocationServiceInterface
{
    public function __construct(
        protected TokenBlacklistModel $blacklistModel,
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected AuditServiceInterface $auditService,
        protected CacheInterface $cache,
        protected BearerTokenService $bearerTokenService,
        private int $accessTokenTtl = 3600,
        private int $revocationCacheTtl = 60
    ) {
    }

    /**
     * Revoke an access token by adding its JTI to blacklist
     */
    public function revokeToken(string $jti, int $expiresAt, ?SecurityContext $context = null): bool
    {
        $added = $this->blacklistModel->addToBlacklist($jti, $expiresAt);

        if (!$added) {
            throw new BadRequestException(
                lang('Tokens.revocationFailed'),
                ['token' => lang('Tokens.revocationFailed')]
            );
        }

        // Mark as revoked in cache immediately for performance
        $cacheKey = "token_revoked_{$jti}";
        $this->cache->save($cacheKey, 1, $this->accessTokenTtl);

        $this->auditService->log('token_revoked', 'tokens', null, [], ['jti' => $jti], $context);

        return true;
    }

    /**
     * Revoke an access token from authorization header
     */
    public function revokeAccessToken(RevokeAccessTokenRequestDTO $request, ?SecurityContext $context = null): bool
    {
        $token = $this->bearerTokenService->extractFromHeader($request->authorization_header);
        if ($token === null) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['authorization_header' => lang('Tokens.invalidAuthorizationFormat')]
            );
        }

        $payload = $this->jwtService->decode($token);

        if (!$payload) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.tokenDecodeFailed')]
            );
        }

        if (!isset($payload->jti, $payload->exp)) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.missingRequiredClaims')]
            );
        }

        return $this->revokeToken($payload->jti, (int) $payload->exp, $context);
    }

    /**
     * Check if a token is revoked (cached check)
     */
    public function isRevoked(string $jti): bool
    {
        $cacheKey = "token_revoked_{$jti}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $isBlacklisted = $this->blacklistModel->isBlacklisted($jti);
        $ttl = $isBlacklisted ? $this->accessTokenTtl : $this->revocationCacheTtl;

        $this->cache->save($cacheKey, $isBlacklisted ? 1 : 0, $ttl);

        return $isBlacklisted;
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): bool
    {
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        $this->auditService->log('all_tokens_revoked', 'users', $userId, [], [], $context);

        return true;
    }

    /**
     * Clean up expired blacklisted tokens
     */
    public function cleanupExpired(): int
    {
        $deletedBlacklist = $this->blacklistModel->deleteExpired();
        $deletedRefresh = $this->refreshTokenModel->deleteExpired();

        return $deletedBlacklist + $deletedRefresh;
    }
}
