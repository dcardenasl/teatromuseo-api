<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Users\UserAccountGuard;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractJwtAuthFilter;

/**
 * Starter-specific JWT auth filter — wires the abstract base into the
 * starter's `JwtService`, token revocation, user model, and the
 * `UserAccountGuard` access policy.
 */
class JwtAuthFilter extends AbstractJwtAuthFilter
{
    protected function decodeToken(string $token): ?object
    {
        $bearer  = Services::bearerTokenService();
        $service = Services::jwtService();

        // The base class already pulled the token from the header; we still
        // delegate to bearerTokenService::extractFromHeader for the format
        // check inside `extractBearerToken()`. Decoding is direct.
        $decoded = $service->decode($token);

        return is_object($decoded) ? $decoded : null;
    }

    protected function extractBearerToken(string $authHeader): ?string
    {
        return Services::bearerTokenService()->extractFromHeader($authHeader);
    }

    protected function shouldCheckRevocation(): bool
    {
        return (bool) config('Api')->jwtRevocationCheck;
    }

    protected function isTokenRevoked(string $jti): bool
    {
        return Services::tokenRevocationService()->isRevoked($jti);
    }

    protected function loadActor(int $userId): ?object
    {
        $userModel = Services::userModel(false);
        $user      = $userModel->find($userId);

        return is_object($user) ? $user : null;
    }

    protected function requireActorOnUserToken(): bool
    {
        return true;
    }

    protected function assertAccessPolicy(object $actor, RequestInterface $request): ?ResponseInterface
    {
        /** @var UserAccountGuard $policy */
        $policy = Services::userAccessPolicyService();

        try {
            $policy->assertCanAuthenticate($actor);
        } catch (AuthorizationException $e) {
            throw $e;
        }

        return null;
    }

    protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface
    {
        return Services::securityAuditLogger();
    }
}
