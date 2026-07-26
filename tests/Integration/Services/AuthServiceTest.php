<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\MeResponseDTO;
use App\DTO\Response\Auth\RegisterResponseDTO;
use App\Entities\UserEntity;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\Auth\VerificationServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Actions\GoogleLoginAction;
use App\Services\Auth\Actions\RegisterUserAction;
use App\Services\Auth\AuthService;
use App\Services\Auth\Support\GoogleAuthHandler;
use App\Services\Auth\Support\SessionManager;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Users\UserAccountGuard;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * AuthService Unit Tests
 *
 * Tests authentication logic with mocked dependencies.
 */
class AuthServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected AuthService $service;
    protected \App\Interfaces\Tokens\JwtServiceInterface $mockJwtService;
    protected \App\Interfaces\Tokens\RefreshTokenServiceInterface $mockRefreshTokenService;
    protected VerificationServiceInterface $mockVerificationService;
    protected AuditServiceInterface $mockAuditService;
    protected GoogleIdentityServiceInterface $mockGoogleIdentityService;
    protected EmailServiceInterface $mockEmailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $this->mockVerificationService = $this->createMock(VerificationServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->mockGoogleIdentityService = $this->createMock(GoogleIdentityServiceInterface::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
    }

    protected function createServiceWithUserQuery(?UserEntity $returnUser): AuthService
    {
        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('findByEmail')->willReturn($returnUser);
        $mockUserRepository->method('findByEmailWithDeleted')->willReturn($returnUser);
        $mockUserRepository->method('find')->willReturn($returnUser);
        $mockUserRepository->method('insert')->willReturn(1);
        $mockUserRepository->method('update')->willReturn(true);
        $mockUserRepository->method('restore')->willReturn(true);

        $permissionsResolver = $this->createMock(EffectivePermissionsResolver::class);
        $permissionsResolver->method('resolve')->willReturn([]);

        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService, $permissionsResolver);
        $userRoleAssignmentService = $this->createMock(\App\Services\Iam\UserRoleAssignmentService::class);
        $registerUserAction = new RegisterUserAction($mockUserRepository, $this->mockVerificationService, $this->mockEmailService, $userRoleAssignmentService);
        $googleLoginAction = new GoogleLoginAction(
            $mockUserRepository,
            $this->mockGoogleIdentityService,
            new GoogleAuthHandler($mockUserRepository, $this->mockRefreshTokenService, $userRoleAssignmentService),
            $sessionManager,
            new UserAccountGuard(),
            $this->mockAuditService,
            $this->mockEmailService
        );

        $updateSelfProfileAction = new \App\Services\Users\Actions\UpdateSelfProfileAction(
            $mockUserRepository,
            $this->createMock(\App\Interfaces\Files\FileRepositoryInterface::class),
            $this->createMock(\App\Interfaces\Files\FileReferenceRepositoryInterface::class),
        );

        return new AuthService(
            $mockUserRepository,
            $registerUserAction,
            $googleLoginAction,
            $this->mockAuditService,
            $sessionManager,
            $permissionsResolver,
            new UserAccountGuard(),
            $updateSelfProfileAction
        );
    }

    // ==================== LOGIN TESTS ====================

    public function testLoginWithValidCredentialsReturnsUserData(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService->method('encode')->willReturn('jwt.access.token');
        $this->mockRefreshTokenService->method('issueRefreshToken')->willReturn('refresh.token');

        $result = $service->login(new LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ], service('validation')));

        $this->assertInstanceOf(LoginResponseDTO::class, $result);
        $this->assertInstanceOf(MeResponseDTO::class, $result->user);
        $data = $result->toArray();
        $this->assertEquals('jwt.access.token', $data['access_token']);
        $this->assertEquals(1, $data['user']['id']);
    }

    public function testLoginWithInvalidPasswordThrowsException(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'password' => password_hash('CorrectPass123!', PASSWORD_BCRYPT),
            'status' => 'active'
        ]);

        $service = $this->createServiceWithUserQuery($user);
        $this->expectException(AuthenticationException::class);

        $service->login(new LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ], service('validation')));
    }

    // ==================== REGISTER TESTS ====================

    public function testRegisterWithValidDataCreatesUser(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'status' => 'pending_approval'
        ]);

        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('find')->willReturn($user);

        $registerUserAction = $this->createMock(RegisterUserAction::class);
        $request = new RegisterRequestDTO([
            'email' => 'new-unique+' . uniqid('', true) . '@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'TestPass456!',
        ], service('validation'));

        $registerUserAction
            ->expects($this->once())
            ->method('execute')
            ->with($request, null)
            ->willReturn($user);

        $googleLoginAction = $this->createMock(GoogleLoginAction::class);
        $permissionsResolver = $this->createMock(EffectivePermissionsResolver::class);
        $permissionsResolver->method('resolve')->willReturn([]);
        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService, $permissionsResolver);

        $service = new AuthService(
            $mockUserRepository,
            $registerUserAction,
            $googleLoginAction,
            $this->mockAuditService,
            $sessionManager,
            $permissionsResolver,
            new UserAccountGuard(),
            new \App\Services\Users\Actions\UpdateSelfProfileAction(
                $mockUserRepository,
                $this->createMock(\App\Interfaces\Files\FileRepositoryInterface::class),
                $this->createMock(\App\Interfaces\Files\FileReferenceRepositoryInterface::class),
            )
        );

        $result = $service->register($request);

        $this->assertInstanceOf(RegisterResponseDTO::class, $result);
    }

    public function testMeReturnsUserProfile(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);
        $service = $this->createServiceWithUserQuery($user);

        $result = $service->me(1);
        $this->assertInstanceOf(MeResponseDTO::class, $result);
        $this->assertEquals('test@example.com', $result->toArray()['email']);
    }
}
