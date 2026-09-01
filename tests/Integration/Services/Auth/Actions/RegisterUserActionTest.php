<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Auth\Actions;

use App\DTO\Request\Auth\RegisterRequestDTO;
use App\Entities\UserEntity;
use App\Interfaces\Auth\VerificationServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Actions\RegisterUserAction;
use App\Services\Iam\UserRoleAssignmentService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * RegisterUserAction Tests
 *
 * RegisterRequestDTO's rules() include `is_unique[users.email]`, which
 * genuinely queries the database during construction — so this lives under
 * tests/Integration (like AuthServiceTest, which constructs the same DTO)
 * even though every other collaborator here is mocked.
 */
class RegisterUserActionTest extends CIUnitTestCase
{
    protected UserRepositoryInterface $mockUserRepository;
    protected VerificationServiceInterface $mockVerificationService;
    protected EmailServiceInterface $mockEmailService;
    protected UserRoleAssignmentService $mockRoleAssignmentService;
    protected RegisterUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockVerificationService = $this->createMock(VerificationServiceInterface::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockRoleAssignmentService = $this->createMock(UserRoleAssignmentService::class);

        $this->action = new RegisterUserAction(
            $this->mockUserRepository,
            $this->mockVerificationService,
            $this->mockEmailService,
            $this->mockRoleAssignmentService
        );
    }

    protected function tearDown(): void
    {
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION');
        parent::tearDown();
    }

    private function registerRequest(): RegisterRequestDTO
    {
        return new RegisterRequestDTO([
            'email' => 'register-action-' . uniqid('', true) . '@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'ValidPass123!',
        ], service('validation'));
    }

    public function testExecuteThrowsWhenInsertFails(): void
    {
        $this->mockUserRepository->method('insert')->willReturn(false);
        $this->mockUserRepository->method('errors')->willReturn(['email' => 'taken']);

        $this->expectException(ValidationException::class);

        $this->action->execute($this->registerRequest());
    }

    public function testExecuteThrowsWhenCreatedUserCannotBeFound(): void
    {
        $this->mockUserRepository->method('insert')->willReturn(42);
        $this->mockUserRepository->method('find')->with(42)->willReturn(null);

        $this->expectException(ValidationException::class);

        $this->action->execute($this->registerRequest());
    }

    public function testExecuteWithVerificationRequiredCreatesPendingUserAndSendsVerificationEmail(): void
    {
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=true');

        $user = new UserEntity(['id' => 42, 'email' => 'pending@example.com', 'status' => 'pending_approval']);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $data): bool => $data['status'] === 'pending_approval' && $data['approved_at'] === null))
            ->willReturn(42);
        $this->mockUserRepository->method('find')->with(42)->willReturn($user);

        $this->mockRoleAssignmentService
            ->expects($this->once())
            ->method('assignRoleByCode')
            ->with(42, 'user');

        $this->mockVerificationService
            ->expects($this->once())
            ->method('sendVerificationEmail')
            ->with(42, null, null);

        $this->mockEmailService->expects($this->never())->method('queueTemplate');

        $result = $this->action->execute($this->registerRequest());

        $this->assertSame($user, $result);
    }

    public function testExecuteSwallowsVerificationEmailFailures(): void
    {
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=true');

        $user = new UserEntity(['id' => 43, 'email' => 'pending2@example.com', 'status' => 'pending_approval']);
        $this->mockUserRepository->method('insert')->willReturn(43);
        $this->mockUserRepository->method('find')->willReturn($user);
        $this->mockVerificationService->method('sendVerificationEmail')->willThrowException(new \RuntimeException('smtp down'));

        // Must not propagate — registration succeeds even if the email fails.
        $result = $this->action->execute($this->registerRequest());

        $this->assertSame($user, $result);
    }

    public function testExecuteWithoutVerificationRequiredCreatesActiveUserAndQueuesApprovalEmail(): void
    {
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=false');

        $user = new UserEntity(['id' => 44, 'email' => 'active@example.com', 'first_name' => 'New', 'status' => 'active']);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $data): bool => $data['status'] === 'active' && $data['approved_at'] !== null))
            ->willReturn(44);
        $this->mockUserRepository->method('find')->with(44)->willReturn($user);

        $this->mockVerificationService->expects($this->never())->method('sendVerificationEmail');

        $this->mockEmailService
            ->expects($this->once())
            ->method('queueTemplate')
            ->with('account-approved', 'active@example.com', $this->callback(static fn (array $data): bool => array_key_exists('login_link', $data) && array_key_exists('display_name', $data)));

        $result = $this->action->execute($this->registerRequest());

        $this->assertSame($user, $result);
    }

    public function testExecuteSwallowsApprovalEmailFailures(): void
    {
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=false');

        $user = new UserEntity(['id' => 45, 'email' => 'active2@example.com', 'status' => 'active']);
        $this->mockUserRepository->method('insert')->willReturn(45);
        $this->mockUserRepository->method('find')->willReturn($user);
        $this->mockEmailService->method('queueTemplate')->willThrowException(new \RuntimeException('queue down'));

        $result = $this->action->execute($this->registerRequest());

        $this->assertSame($user, $result);
    }
}
