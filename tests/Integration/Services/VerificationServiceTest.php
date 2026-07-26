<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Entities\UserEntity;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\VerificationService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * VerificationService Unit Tests
 *
 * Tests email verification flow with mocked dependencies.
 */
class VerificationServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected VerificationService $service;
    protected EmailServiceInterface $mockEmailService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
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
    private function createServiceWithUser(?UserEntity $user, bool $allowUpdate = true): VerificationService
    {
        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('find')->willReturn($user);
        $mockUserRepository->method('findByVerificationToken')->willReturn($user);

        $mockUserRepository->method('withAuditAction')->willReturnSelf();

        $mockUserRepository->method('update')->willReturnCallback(function ($id, $row) use ($user, $allowUpdate) {
            if (!$allowUpdate) {
                return false;
            }
            if ($user && is_array($row)) {
                foreach ($row as $key => $value) {
                    $user->{$key} = $value;
                }
            }
            return true;
        });

        return new VerificationService(
            $mockUserRepository,
            $this->mockEmailService,
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

    // ==================== SEND VERIFICATION EMAIL TESTS ====================

    public function testSendVerificationEmailCreatesTokenAndSends(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'verification',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['verification_link'])
                        && str_contains($data['verification_link'], '/verify-email?token=')
                        && str_contains($data['verification_link'], 'token=');
                })
            );

        $result = $service->sendVerificationEmail(1);

        $this->assertTrue($result);
    }

    public function testSendVerificationEmailUsesAllowedClientBaseUrl(): void
    {
        putenv('WEBAPP_BASE_URL=https://fallback.example.com');
        putenv('WEBAPP_ALLOWED_BASE_URLS=https://fallback.example.com,https://admin.example.com');

        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'verification',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['verification_link'])
                        && str_starts_with($data['verification_link'], 'https://fallback.example.com/verify-email');
                })
            );

        // SecurityContext is now the second parameter
        $service->sendVerificationEmail(1, new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(1));
    }

    public function testSendVerificationEmailThrowsForAlreadyVerified(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'verified@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ConflictException::class);

        $service->sendVerificationEmail(1);
    }

    public function testSendVerificationEmailThrowsForNonExistentUser(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->sendVerificationEmail(999);
    }

    // ==================== VERIFY EMAIL TESTS ====================

    public function testVerifyEmailSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'email_verification_token' => 'valid-token-123',
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'valid-token-123',
            'email' => 'test@example.com'
        ], service('validation')));

        $this->assertTrue($result);

        // Verify the user entity was updated
        $this->assertNotNull($user->email_verified_at);
    }

    public function testVerifyEmailFailsForExpiredToken(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'email_verification_token' => 'expired-token',
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(BadRequestException::class);

        $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'expired-token',
            'email' => 'test@example.com'
        ], service('validation')));
    }

    public function testVerifyEmailFailsForInvalidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'invalid-token',
            'email' => 'test@example.com'
        ], service('validation')));
    }

    public function testVerifyEmailThrowsForEmptyToken(): void
    {
        // Validation now happens in DTO constructor
        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\ValidationException::class);

        new \App\DTO\Request\Identity\VerificationRequestDTO(['token' => ''], service('validation'));
    }

    // ==================== RESEND VERIFICATION TESTS ====================

    public function testResendVerificationSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with('verification', 'test@example.com', $this->anything());

        $result = $service->resendVerification(1);

        $this->assertTrue($result);
    }

    public function testResendVerificationThrowsForAlreadyVerified(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'verified@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ConflictException::class);

        $service->resendVerification(1);
    }

    public function testResendVerificationThrowsForNonExistentUser(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->resendVerification(999);
    }
}
