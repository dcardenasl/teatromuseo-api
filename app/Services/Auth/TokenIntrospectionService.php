<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\DTO\Response\Auth\IntrospectResponseDTO;
use App\Interfaces\Auth\TokenIntrospectionServiceInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\TokenRevocationServiceInterface;
use App\Models\UserModel;
use App\Services\Iam\EffectivePermissionsResolver;

/**
 * Validates JWTs on behalf of external callers (domain apps).
 *
 * Mirrors the validation flow in JwtAuthFilter: decode → check revocation
 * → return claims. Never throws; outcomes are encoded in the response DTO.
 *
 * The JWT's `scope` claim is application-bound at issue time (currently to
 * the hub's `self` application). To make introspect useful from a domain
 * app, the caller's `applicationId` (resolved from `X-App-Key` by the
 * AppKeyRequiredFilter) is passed in and used to re-resolve the user's
 * effective permissions for *that* application.
 */
class TokenIntrospectionService implements TokenIntrospectionServiceInterface
{
    public function __construct(
        private readonly JwtServiceInterface $jwtService,
        private readonly TokenRevocationServiceInterface $tokenRevocationService,
        private readonly EffectivePermissionsResolver $effectivePermissionsResolver,
        private readonly UserModel $userModel,
    ) {
    }

    public function introspect(IntrospectRequestDTO $request, ?int $applicationId = null): IntrospectResponseDTO
    {
        $decoded = $this->jwtService->decode($request->token);

        if ($decoded === null) {
            return $this->invalid('invalid_or_expired');
        }

        $jti = isset($decoded->jti) ? (string) $decoded->jti : null;
        if ($jti !== null && $this->tokenRevocationService->isRevoked($jti)) {
            return $this->invalid('revoked');
        }

        $uid = isset($decoded->uid) ? (int) $decoded->uid : null;

        if ($uid !== null && $uid > 0) {
            $user = $this->userModel->find($uid);
            if ($user === null) {
                return $this->invalid('invalid_or_expired');
            }

            $tokenVersion = isset($decoded->token_version)
                ? (int) $decoded->token_version
                : 0;
            $currentVersion = max(0, (int) ($user->auth_token_version ?? 0));
            if ($tokenVersion !== $currentVersion) {
                return $this->invalid('revoked');
            }
        }

        $permissions = $this->resolvePermissions($decoded, $uid, $applicationId);

        return new IntrospectResponseDTO(
            valid: true,
            uid: $uid,
            permissions: $permissions,
            exp: isset($decoded->exp) ? (int) $decoded->exp : null,
            error: null,
            app_id: $applicationId,
        );
    }

    /**
     * @return list<string>
     */
    private function resolvePermissions(object $decoded, ?int $uid, ?int $applicationId): array
    {
        // User token + caller's application known → re-resolve scope so the
        // domain app receives its own permissions for this user. Service
        // tokens (no uid) fall through to the JWT-baked scope.
        if ($uid !== null && $uid > 0 && $applicationId !== null) {
            return $this->effectivePermissionsResolver->resolve($uid, $applicationId);
        }

        if (isset($decoded->scope) && is_array($decoded->scope)) {
            return array_values(array_map(static fn ($p) => (string) $p, $decoded->scope));
        }

        return [];
    }

    private function invalid(string $reason): IntrospectResponseDTO
    {
        return new IntrospectResponseDTO(
            valid: false,
            uid: null,
            permissions: [],
            exp: null,
            error: $reason,
        );
    }
}
