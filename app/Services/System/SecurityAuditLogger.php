<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Entities\ApiKeyEntity;
use CodeIgniter\HTTP\RequestInterface;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory;

/**
 * Centralized, non-blocking audit logger for cross-cutting security events.
 *
 * Implements the core contract (used by `AbstractPermissionFilter`,
 * `AbstractJwtAuthFilter`, and the IAM authorization service) and
 * extends it with starter-specific hooks for `api_keys` events.
 */
class SecurityAuditLogger implements SecurityAuditLoggerInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly RequestAuditContextFactory $contextFactory
    ) {
    }

    public function logAuthorizationDeniedFromRequest(
        RequestInterface $request,
        string $required,
        ?string $actorContext,
        ?int $actorId,
        string $action = 'authorization_denied_permission'
    ): void {
        $payload = [
            'required'      => $required,
            'actor_context' => $actorContext,
            'path'          => $this->extractPath($request),
        ];

        $context = $this->contextFactory->createContext($request, $actorId);

        $this->safeLog($action, 'authorization', null, [], $payload, $context, 'denied', 'critical');
    }

    /** @phpstan-ignore dtoFirst.arrayParameter */
    public function logAuthorizationDeniedFromContext(string $action, array $details, ?SecurityContext $context): void
    {
        $this->safeLog($action, 'authorization', null, [], $details, $context, 'denied', 'critical');
    }

    public function logApiKeyAuthFailure(string $rawKey, RequestInterface $request): void
    {
        $payload = [
            'key_prefix' => substr($rawKey, 0, 12),
            'path' => $this->extractPath($request),
        ];

        $context = $this->contextFactory->createContext($request);

        $this->safeLog('api_key_auth_failed', 'api_keys', null, [], $payload, $context, 'failure', 'critical');
    }

    public function logApiKeyRateLimitExceeded(ApiKeyEntity $apiKey, string $ip, ?int $userId, string $scope): void
    {
        $payload = [
            'scope' => $scope,
            'ip_address' => $ip,
            'user_id' => $userId,
        ];

        $context = new SecurityContext($userId, ['ip_address' => $ip]);

        $this->safeLog('api_key_rate_limit_exceeded', 'api_keys', (int) $apiKey->id, [], $payload, $context, 'denied', 'warning');
    }

    public function logRevokedTokenReuse(RequestInterface $request, ?int $userId, ?string $userContext, string $jti): void
    {
        $context = $this->contextFactory->createContext($request, $userId);
        $this->safeLog(
            'revoked_token_reuse_detected',
            'tokens',
            null,
            [],
            ['jti' => $jti],
            $context,
            'denied',
            'critical'
        );
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function safeLog(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context,
        string $result,
        string $severity
    ): void {
        try {
            $this->auditService->log(
                $action,
                $entityType,
                $entityId,
                $oldValues,
                $newValues,
                $context,
                $result,
                $severity
            );
        } catch (\Throwable) {
            // Security auditing should not alter primary control flow.
        }
    }

    private function extractPath(RequestInterface $request): string
    {
        return method_exists($request, 'getPath') ? (string) $request->getPath() : '';
    }
}
