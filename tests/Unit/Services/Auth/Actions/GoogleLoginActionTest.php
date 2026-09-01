<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth\Actions;

use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\Response\Identity\GoogleIdentityResponseDTO;
use App\Entities\UserEntity;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Actions\GoogleLoginAction;
use App\Services\Auth\Support\GoogleAuthHandler;
use App\Services\Auth\Support\SessionManager;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Users\UserAccountGuard;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\OperationState;

/**
 * GoogleLoginAction Unit Tests
 */
class GoogleLoginActionTest extends CIUnitTestCase
{
    protected UserRepositoryInterface $mockUserRepository;
    protected GoogleIdentityServiceInterface $mockGoogleIdentityService;
    protected GoogleAuthHandler $mockGoogleHandler;
    protected AuditServiceInterface $mockAuditService;
    protected EmailServiceInterface $mockEmailService;
    protected JwtServiceInterface $mockJwtService;
    protected RefreshTokenServiceInterface $mockRefreshTokenService;
    protected GoogleLoginAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockGoogleIdentityService = $this->createMock(GoogleIdentityServiceInterface::class);
        $this->mockGoogleHandler = $this->createMock(GoogleAuthHandler::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);

        $permissionsResolver = $this->createMock(EffectivePermissionsResolver::class);
        $permissionsResolver->method('resolveAll')->willReturn([]);
        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService, $permissionsResolver);

        $this->action = new GoogleLoginAction(
            $this->mockUserRepository,
            $this->mockGoogleIdentityService,
            $this->mockGoogleHandler,
            $sessionManager,
            new UserAccountGuard(),
            $this->mockAuditService,
            $this->mockEmailService
        );
    }

    private function identity(string $email = 'google@example.com'): GoogleIdentityResponseDTO
    {
        return GoogleIdentityResponseDTO::fromArray([
            'provider_id' => 'g-123',
            'email' => $email,
            'first_name' => 'Google',
            'last_name' => 'User',
            'avatar_url' => null,
            'claims' => [],
        ]);
    }

    private function request(): GoogleLoginRequestDTO
    {
        return new GoogleLoginRequestDTO(['id_token' => 'a-google-id-token'], service('validation'));
    }

    public function testExecuteAuditsAndRethrowsWhenTokenVerificationFails(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willThrowException(new \RuntimeException('bad token'));

        $this->mockAuditService
            ->expects($this->once())
            ->method('log')
            ->with(
                'google_login_failure',
                'users',
                null,
                [],
                ['reason' => 'invalid_google_token'],
                null,
                'failure',
                'warning'
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bad token');

        $this->action->execute($this->request());
    }

    public function testExecuteCreatesPendingUserWhenNoneExists(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn(null);

        $pending = new UserEntity(['id' => 10, 'email' => 'google@example.com', 'status' => 'pending_approval']);
        $this->mockGoogleHandler->expects($this->once())->method('createPendingUser')->willReturn($pending);

        $this->mockEmailService->expects($this->once())->method('queueTemplate');
        $this->mockAuditService->expects($this->once())->method('log')->with(
            'google_registration_pending',
            'users',
            10,
            [],
            ['email' => 'google@example.com', 'provider' => 'google'],
            $this->isInstanceOf(SecurityContext::class)
        );

        $result = $this->action->execute($this->request());

        $this->assertSame(OperationState::ACCEPTED, $result->state);
    }

    public function testExecuteReactivatesSoftDeletedUser(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $deletedUser = new UserEntity(['id' => 11, 'email' => 'google@example.com', 'deleted_at' => date('Y-m-d H:i:s')]);
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn($deletedUser);

        $reactivated = new UserEntity(['id' => 11, 'email' => 'google@example.com', 'status' => 'pending_approval']);
        $this->mockGoogleHandler->expects($this->once())->method('reactivateDeletedUser')->willReturn($reactivated);

        $this->mockEmailService->expects($this->once())->method('queueTemplate');

        $result = $this->action->execute($this->request());

        $this->assertSame(OperationState::ACCEPTED, $result->state);
    }

    public function testExecuteSwallowsPendingApprovalEmailFailures(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn(null);
        $pending = new UserEntity(['id' => 12, 'email' => 'google@example.com']);
        $this->mockGoogleHandler->method('createPendingUser')->willReturn($pending);
        $this->mockEmailService->method('queueTemplate')->willThrowException(new \RuntimeException('smtp down'));

        $result = $this->action->execute($this->request());

        $this->assertSame(OperationState::ACCEPTED, $result->state);
    }

    public function testExecuteLinksGoogleToUnlinkedActiveUserAndLogsIn(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $existing = new UserEntity([
            'id' => 20,
            'email' => 'google@example.com',
            'status' => 'active',
            'oauth_provider' => null,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $refreshed = new UserEntity([
            'id' => 20,
            'email' => 'google@example.com',
            'status' => 'active',
            'oauth_provider' => 'google',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn($existing);
        $this->mockUserRepository->method('withAuditAction')->willReturn($this->mockUserRepository);
        $this->mockUserRepository
            ->expects($this->once())
            ->method('update')
            ->with(20, $this->callback(static fn (array $data): bool => $data['oauth_provider'] === 'google'));
        $this->mockUserRepository->method('find')->willReturn($refreshed);

        $this->mockJwtService->method('encode')->willReturn('access-token');
        $this->mockRefreshTokenService->method('issueRefreshToken')->willReturn('refresh-token');

        // updateData !== [] here, so the success audit log is NOT sent by
        // GoogleLoginAction directly (the update() call carries its own
        // 'google_login_success' audit action instead).
        $this->mockAuditService->expects($this->never())->method('log');

        $result = $this->action->execute($this->request());

        $this->assertSame(OperationState::SUCCESS, $result->state);
    }

    public function testExecuteLogsInAlreadyLinkedUserWithoutUpdate(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $existing = new UserEntity([
            'id' => 21,
            'email' => 'google@example.com',
            'status' => 'active',
            'oauth_provider' => 'google',
            'oauth_provider_id' => 'g-123',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn($existing);
        $this->mockUserRepository->method('find')->willReturn($existing);
        $this->mockUserRepository->expects($this->never())->method('update');

        $this->mockJwtService->method('encode')->willReturn('access-token');
        $this->mockRefreshTokenService->method('issueRefreshToken')->willReturn('refresh-token');

        $this->mockAuditService
            ->expects($this->once())
            ->method('log')
            ->with(
                'google_login_success',
                'users',
                21,
                [],
                ['email' => 'google@example.com', 'provider' => 'google'],
                $this->anything()
            );

        $result = $this->action->execute($this->request());

        $this->assertSame(OperationState::SUCCESS, $result->state);
    }

    public function testExecuteThrowsWhenUserDisappearsAfterUpdate(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $existing = new UserEntity([
            'id' => 22,
            'email' => 'google@example.com',
            'status' => 'active',
            'oauth_provider' => null,
        ]);
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn($existing);
        $this->mockUserRepository->method('withAuditAction')->willReturn($this->mockUserRepository);
        $this->mockUserRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->action->execute($this->request());
    }

    public function testExecuteThrowsWhenAccountCannotAuthenticate(): void
    {
        $this->mockGoogleIdentityService->method('verifyIdToken')->willReturn($this->identity());
        $existing = new UserEntity([
            'id' => 23,
            'email' => 'google@example.com',
            'status' => 'pending_approval',
            'oauth_provider' => 'google',
            'oauth_provider_id' => 'g-123',
        ]);
        $this->mockUserRepository->method('findByEmailWithDeleted')->willReturn($existing);

        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException::class);

        $this->action->execute($this->request());
    }
}
