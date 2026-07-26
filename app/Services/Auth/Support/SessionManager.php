<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\MeResponseDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Services\Iam\EffectivePermissionsResolver;

/**
 * Session Manager
 *
 * Issues access (JWT) + refresh tokens and assembles the canonical
 * session response. The `user` field is always a `MeResponseDTO`
 * (id, email, first/last name, status, avatar, timestamps, roles,
 * permissions) — the same shape returned by `/auth/me` and `PATCH
 * /auth/me`, so consumers can persist it as-is from any of the four
 * endpoints without losing fields.
 */
class SessionManager
{
    public function __construct(
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected EffectivePermissionsResolver $permissionsResolver,
        protected int $accessTokenTtl = 3600
    ) {
    }

    /**
     * Build a session response (access + refresh tokens + canonical user).
     *
     * The returned DTO is consumed directly by AuthService::login() and by
     * Google login flows wrapped in an OperationResult.
     */
    public function generateSessionResponse(object $user): LoginResponseDTO
    {
        $userId = (int) ($user->id ?? 0);
        $permissions = $userId > 0
            ? $this->permissionsResolver->resolveAll($userId)
            : [];

        $accessToken = $this->jwtService->encode($userId, $permissions);
        $refreshToken = $this->refreshTokenService->issueRefreshToken($userId);

        $userPayload = MeResponseDTO::fromUserData(
            $this->normalizeUser($user),
            $permissions
        );

        return new LoginResponseDTO(
            access_token: $accessToken,
            refresh_token: $refreshToken,
            expires_in: $this->accessTokenTtl,
            user: $userPayload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUser(object $user): array
    {
        if (method_exists($user, 'toArray')) {
            $array = $user->toArray();
            if (is_array($array)) {
                return $array;
            }
        }

        return get_object_vars($user);
    }
}
