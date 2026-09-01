<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System;

use App\Entities\ApiKeyEntity;
use App\Services\System\SecurityAuditLogger;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory;

final class SecurityAuditLoggerTest extends CIUnitTestCase
{
    public function testLogApiKeyAuthFailureWritesExpectedEvent(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(static fn (string $header): string => match (strtolower($header)) {
                'x-request-id' => 'req-audit-01',
                'user-agent' => 'Test-UA',
                default => '',
            });
        $request->method('getIPAddress')->willReturn('192.168.1.10');

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'api_key_auth_failed',
                'api_keys',
                null,
                [],
                $this->callback(static fn (array $payload): bool => isset($payload['key_prefix']) && strlen((string) $payload['key_prefix']) <= 12),
                $this->isInstanceOf(SecurityContext::class),
                'failure',
                'critical'
            );

        $logger->logApiKeyAuthFailure('apk_12345678901234567890', $request);
    }

    public function testLogAuthorizationDeniedFromContextWritesDeniedCritical(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());
        $context = new SecurityContext(10, ['request_id' => 'req-123']);

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'authorization_denied_resource',
                'authorization',
                null,
                [],
                ['rule' => 'test'],
                $context,
                'denied',
                'critical'
            );

        $logger->logAuthorizationDeniedFromContext('authorization_denied_resource', ['rule' => 'test'], $context);
    }

    public function testLogApiKeyRateLimitExceededWritesExpectedEntity(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $apiKey = new ApiKeyEntity(['id' => 42]);

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'api_key_rate_limit_exceeded',
                'api_keys',
                42,
                [],
                $this->callback(static fn (array $payload): bool => ($payload['scope'] ?? null) === 'ip'),
                $this->isInstanceOf(SecurityContext::class),
                'denied',
                'warning'
            );

        $logger->logApiKeyRateLimitExceeded($apiKey, '10.1.1.1', null, 'ip');
    }

    public function testLogAuthorizationDeniedFromRequestWritesExpectedEvent(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getIPAddress')->willReturn('192.168.1.20');

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'authorization_denied_permission',
                'authorization',
                null,
                [],
                $this->callback(static fn (array $payload): bool => $payload['required'] === 'users.write' && $payload['actor_context'] === 'user:5'),
                $this->isInstanceOf(SecurityContext::class),
                'denied',
                'critical'
            );

        $logger->logAuthorizationDeniedFromRequest($request, 'users.write', 'user:5', 5);
    }

    public function testLogAuthorizationDeniedFromRequestUsesCustomAction(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getIPAddress')->willReturn('192.168.1.21');

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'custom_denied_action',
                'authorization',
                null,
                [],
                $this->anything(),
                $this->isInstanceOf(SecurityContext::class),
                'denied',
                'critical'
            );

        $logger->logAuthorizationDeniedFromRequest($request, 'iam.admin-access', null, null, 'custom_denied_action');
    }

    public function testLogRevokedTokenReuseWritesExpectedEvent(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getIPAddress')->willReturn('192.168.1.22');

        $mockAudit->expects($this->once())
            ->method('log')
            ->with(
                'revoked_token_reuse_detected',
                'tokens',
                null,
                [],
                ['jti' => 'jti-123'],
                $this->isInstanceOf(SecurityContext::class),
                'denied',
                'critical'
            );

        $logger->logRevokedTokenReuse($request, 7, 'user:7', 'jti-123');
    }

    public function testSafeLogSwallowsAuditServiceExceptions(): void
    {
        $mockAudit = $this->createMock(AuditServiceInterface::class);
        $mockAudit->method('log')->willThrowException(new \RuntimeException('audit backend down'));
        $logger = new SecurityAuditLogger($mockAudit, new RequestAuditContextFactory());

        $context = new SecurityContext(1);

        // Must not propagate — audit logging is intentionally non-blocking.
        $logger->logAuthorizationDeniedFromContext('some_action', [], $context);
        $this->addToAssertionCount(1);
    }
}
