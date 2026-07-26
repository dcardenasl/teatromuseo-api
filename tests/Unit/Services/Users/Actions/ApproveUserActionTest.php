<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Actions;

use App\Entities\UserEntity;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Users\Actions\ApproveUserAction;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

class ApproveUserActionTest extends CIUnitTestCase
{
    protected UserRepositoryInterface $mockUserRepository;
    protected EmailServiceInterface $emailService;
    protected ApproveUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockUserRepository->method('withAuditAction')->willReturnSelf();
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->action = new ApproveUserAction($this->mockUserRepository, $this->emailService);
    }

    public function testExecuteThrowsNotFoundWhenUserDoesNotExist(): void
    {
        $this->mockUserRepository->expects($this->once())->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->action->execute(999, new SecurityContext(1));
    }

    public function testExecuteThrowsConflictWhenUserAlreadyActive(): void
    {
        $user = new UserEntity(['id' => 1, 'email' => 'active@example.com', 'status' => 'active']);
        $this->mockUserRepository->expects($this->once())->method('find')->with(1)->willReturn($user);
        $this->mockUserRepository->expects($this->never())->method('update');

        $this->expectException(ConflictException::class);
        $this->action->execute(1, new SecurityContext(1));
    }

    public function testExecuteThrowsConflictWhenUserIsInvited(): void
    {
        $user = new UserEntity(['id' => 1, 'email' => 'invited@example.com', 'status' => 'invited']);
        $this->mockUserRepository->expects($this->once())->method('find')->with(1)->willReturn($user);
        $this->mockUserRepository->expects($this->never())->method('update');

        $this->expectException(ConflictException::class);
        $this->action->execute(1, new SecurityContext(1));
    }

    public function testExecuteThrowsConflictWhenUserIsInInvalidState(): void
    {
        $user = new UserEntity(['id' => 1, 'email' => 'blocked@example.com', 'status' => 'blocked']);
        $this->mockUserRepository->expects($this->once())->method('find')->with(1)->willReturn($user);
        $this->mockUserRepository->expects($this->never())->method('update');

        $this->expectException(ConflictException::class);
        $this->action->execute(1, new SecurityContext(1));
    }

    public function testExecuteApprovesPendingUserAndQueuesEmail(): void
    {
        $context = new SecurityContext(99);
        $pendingUser = new UserEntity([
            'id' => 7,
            'email' => 'pending@example.com',
            'first_name' => 'Pending',
            'last_name' => 'User',
            'status' => 'pending_approval',
        ]);
        $approvedUser = new UserEntity([
            'id' => 7,
            'email' => 'pending@example.com',
            'first_name' => 'Pending',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        $this->mockUserRepository->expects($this->exactly(2))
            ->method('find')
            ->with(7)
            ->willReturnOnConsecutiveCalls($pendingUser, $approvedUser);

        $this->mockUserRepository->expects($this->once())
            ->method('withAuditAction')
            ->with('user_approved')
            ->willReturnSelf();

        $this->mockUserRepository->expects($this->once())
            ->method('update')
            ->with(7, $this->callback(function (array $data): bool {
                return $data['status'] === 'active'
                    && isset($data['approved_at'])
                    && $data['approved_by'] === 99;
            }));

        $this->emailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'account-approved',
                'pending@example.com',
                $this->callback(function (array $data): bool {
                    return isset($data['subject'], $data['display_name'], $data['login_link'])
                        && is_string($data['login_link'])
                        && str_ends_with($data['login_link'], '/login');
                })
            )
            ->willReturn(1);

        $result = $this->action->execute(7, $context);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertEquals('active', $result->status);
    }
}
