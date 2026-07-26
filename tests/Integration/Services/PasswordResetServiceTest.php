<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Entities\UserEntity;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Models\PasswordResetModel;
use App\Services\Auth\PasswordResetService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * PasswordResetService Unit Tests
 */
class PasswordResetServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    private const VALID_RESET_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const UNKNOWN_RESET_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected PasswordResetService $service;
    protected UserRepositoryInterface $mockUserRepository;
    protected PasswordResetModel $mockPasswordResetModel;
    protected EmailServiceInterface $mockEmailService;
    protected RefreshTokenServiceInterface $mockRefreshTokenService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
    }

    protected function tearDown(): void
    {
        putenv('WEBAPP_BASE_URL');
        putenv('WEBAPP_ALLOWED_BASE_URLS');

        parent::tearDown();
    }

    /**
     * Create service with mocked user repository
     */
    private function createServiceWithUser(
        ?UserEntity $activeUser,
        ?UserEntity $deletedUser = null
    ): PasswordResetService {
        $validToken = self::VALID_RESET_TOKEN;

        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('findByEmail')->willReturn($activeUser);
        $mockUserRepository->method('findByEmailWithDeleted')->willReturn($activeUser ?? $deletedUser);
        $mockUserRepository->method('update')->willReturn(true);
        $mockUserRepository->method('restore')->willReturn(true);

        $mockPasswordResetModel = new class ($validToken) extends PasswordResetModel {
            private string $validToken;

            public function __construct(string $validToken)
            {
                $this->validToken = $validToken;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function delete($id = null, bool $purge = false)
            {
                return true;
            }

            public function insert($row = null, bool $returnID = true)
            {
                return 1;
            }

            public function cleanExpired(int $expiryMinutes = 60): void
            {
                // Do nothing in mock
            }

            public function isValidToken(string $email, string $token, int $expiryMinutes = 60): bool
            {
                return $token === $this->validToken;
            }
        };

        return new PasswordResetService(
            $mockUserRepository,
            $mockPasswordResetModel,
            $this->mockEmailService,
            $this->mockRefreshTokenService,
            $this->mockAuditService
        );
    }

    /**
     * Helper: Create user entity
     */
    private function createUserEntity(array $data): UserEntity
    {
        $user = new UserEntity();
        foreach ($data as $key => $value) {
            $user->{$key} = $value;
        }

        return $user;
    }

    // ==================== SEND RESET LINK TESTS ====================

    public function testSendResetLinkCreatesTokenAndSendsEmail(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'password-reset',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['reset_link'])
                        && str_contains($data['reset_link'], '/reset-password?token=')
                        && str_contains($data['reset_link'], 'token=');
                })
            );

        $result = $service->sendResetLink(new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'test@example.com'], service('validation')));

        $this->assertTrue($result);
    }

    public function testSendResetLinkPreventsEmailEnumeration(): void
    {
        // Test with non-existent user
        $service = $this->createServiceWithUser(null);

        $this->mockEmailService->expects($this->never())
            ->method('queueTemplate');

        $result = $service->sendResetLink(new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'nonexistent@example.com'], service('validation')));

        $this->assertTrue($result);
    }

    public function testSendResetLinkThrowsExceptionForInvalidEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'invalid-email'], service('validation'));
    }

    public function testSendResetLinkThrowsExceptionForEmptyEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => ''], service('validation'));
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenSucceedsForValidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $request = new \App\DTO\Request\Identity\PasswordResetTokenValidationDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
        ], service('validation'));

        $result = $service->validateToken($request);

        $this->assertTrue($result);
    }

    public function testValidateTokenFailsForInvalidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $request = new \App\DTO\Request\Identity\PasswordResetTokenValidationDTO([
            'email' => 'test@example.com',
            'token' => self::UNKNOWN_RESET_TOKEN,
        ], service('validation'));

        $service->validateToken($request);
    }

    // ==================== RESET PASSWORD TESTS ====================

    public function testResetPasswordSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->resetPassword(new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'NewSecure123!',
        ], service('validation')));

        $this->assertTrue($result);
    }

    public function testResetPasswordThrowsExceptionForWeakPassword(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\ValidationException::class);

        new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'weak',
        ], service('validation'));
    }

    public function testResetPasswordActivatesInvitedUser(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'invited@example.com',
            'status' => 'invited',
            'invited_by' => 2,
            'invited_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->resetPassword(new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'invited@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'NewPassword123!',
        ], service('validation')));

        $this->assertTrue($result);
    }

    public function testResetPasswordActivatesActiveInvitedUser(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'active-invited@example.com',
            'status' => 'active',
            'invited_by' => 2,
            'invited_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->resetPassword(new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'active-invited@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'NewPassword123!',
        ], service('validation')));

        $this->assertTrue($result);
    }

    public function testResetPasswordThrowsExceptionForMissingFields(): void
    {
        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            // Missing password
        ], service('validation'));
    }
}
