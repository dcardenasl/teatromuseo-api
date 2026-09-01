<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Actions;

use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\Entities\FileEntity;
use App\Entities\UserEntity;
use App\Interfaces\Files\FileReferenceRepositoryInterface;
use App\Interfaces\Files\FileRepositoryInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\IamAuthorizationService;
use App\Services\Iam\UserRoleAssignmentService;
use App\Services\Users\Actions\UpdateUserAction;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * UpdateUserAction Unit Tests
 */
class UpdateUserActionTest extends CIUnitTestCase
{
    protected UserRepositoryInterface $mockUserRepository;
    protected UserRoleAssignmentService $mockRoleAssignmentService;
    protected IamAuthorizationService $mockAuthz;
    protected FileRepositoryInterface $mockFileRepository;
    protected FileReferenceRepositoryInterface $mockFileReferenceRepository;
    protected UpdateUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockRoleAssignmentService = $this->createMock(UserRoleAssignmentService::class);
        $this->mockAuthz = $this->createMock(IamAuthorizationService::class);
        $this->mockFileRepository = $this->createMock(FileRepositoryInterface::class);
        $this->mockFileReferenceRepository = $this->createMock(FileReferenceRepositoryInterface::class);

        $this->action = new UpdateUserAction(
            $this->mockUserRepository,
            $this->mockRoleAssignmentService,
            $this->mockAuthz,
            $this->mockFileRepository,
            $this->mockFileReferenceRepository
        );
    }

    public function testExecuteThrowsWhenUserNotFound(): void
    {
        $this->mockUserRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->action->execute(1, new UserUpdateRequestDTO(['first_name' => 'A'], service('validation')));
    }

    public function testExecuteThrowsWhenNoFieldsAndNoRoleUpdates(): void
    {
        $this->mockUserRepository->method('find')->willReturn(new UserEntity(['id' => 1, 'email' => 'a@example.com']));

        $this->expectException(ValidationException::class);

        $this->action->execute(1, new UserUpdateRequestDTO([], service('validation')));
    }

    public function testExecuteThrowsWhenNonSuperadminChangesEmail(): void
    {
        $this->mockUserRepository->method('find')->willReturn(new UserEntity(['id' => 1, 'email' => 'old@example.com']));
        $this->mockAuthz->method('isSuperAdmin')->willReturn(false);

        $this->expectException(AuthorizationException::class);

        $this->action->execute(1, new UserUpdateRequestDTO(['email' => 'new@example.com'], service('validation')));
    }

    public function testExecuteAllowsSuperadminToChangeEmail(): void
    {
        $before = new UserEntity(['id' => 1, 'email' => 'old@example.com']);
        $after = new UserEntity(['id' => 1, 'email' => 'new@example.com']);

        $this->mockUserRepository->method('find')->willReturnOnConsecutiveCalls($before, $after);
        $this->mockAuthz->method('isSuperAdmin')->willReturn(true);
        $this->mockUserRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, ['email' => 'new@example.com']);

        $result = $this->action->execute(1, new UserUpdateRequestDTO(['email' => 'new@example.com'], service('validation')));

        $this->assertSame('new@example.com', $result->email);
    }

    public function testExecuteUpdatesFieldsOnly(): void
    {
        $before = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'first_name' => 'Old']);
        $after = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'first_name' => 'New']);

        $this->mockUserRepository->method('find')->willReturnOnConsecutiveCalls($before, $after);
        $this->mockUserRepository->expects($this->once())->method('update')->with(1, ['first_name' => 'New']);
        $this->mockRoleAssignmentService->expects($this->never())->method('syncRoles');

        $result = $this->action->execute(1, new UserUpdateRequestDTO(['first_name' => 'New'], service('validation')));

        $this->assertSame('New', $result->first_name);
    }

    public function testExecuteSyncsRolesWhenRoleIdsProvided(): void
    {
        $user = new UserEntity(['id' => 1, 'email' => 'a@example.com']);
        $this->mockUserRepository->method('find')->willReturn($user);
        $this->mockRoleAssignmentService
            ->expects($this->once())
            ->method('syncRoles')
            ->with(1, [3, 4], 99);

        $context = new SecurityContext(99);

        $this->action->execute(1, new UserUpdateRequestDTO(['role_ids' => [3, 4]], service('validation')), $context);
    }

    public function testExecuteThrowsWhenUpdatedUserCannotBeRefetched(): void
    {
        $this->mockUserRepository->method('find')->willReturnOnConsecutiveCalls(
            new UserEntity(['id' => 1, 'email' => 'a@example.com']),
            null
        );

        $this->expectException(NotFoundException::class);

        $this->action->execute(1, new UserUpdateRequestDTO(['first_name' => 'X'], service('validation')));
    }

    public function testExecuteRegistersNewAvatarReferenceWhenFileFound(): void
    {
        $before = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $after = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'avatar_url' => 'https://cdn.test/a.jpg', 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $this->mockUserRepository->method('find')->willReturnOnConsecutiveCalls($before, $after);

        $this->mockFileReferenceRepository
            ->expects($this->once())
            ->method('unregisterByResource')
            ->with('User', 1, 'avatar');

        $file = new FileEntity(['id' => 55]);
        $this->mockFileRepository->method('findByUrl')->with('https://cdn.test/a.jpg')->willReturn($file);

        $this->mockFileReferenceRepository
            ->expects($this->once())
            ->method('register')
            ->with(55, 'User', 1, 'avatar', 'Jane Doe');

        $this->action->execute(1, new UserUpdateRequestDTO(['avatar_url' => 'https://cdn.test/a.jpg'], service('validation')));
    }

    public function testExecuteUnregistersAvatarWhenClearedToNull(): void
    {
        $before = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'avatar_url' => 'https://cdn.test/old.jpg']);
        $after = new UserEntity(['id' => 1, 'email' => 'a@example.com', 'avatar_url' => null]);
        $this->mockUserRepository->method('find')->willReturnOnConsecutiveCalls($before, $after);

        $this->mockFileReferenceRepository
            ->expects($this->once())
            ->method('unregisterByResource')
            ->with('User', 1, 'avatar');
        $this->mockFileRepository->expects($this->never())->method('findByUrl');
        $this->mockFileReferenceRepository->expects($this->never())->method('register');

        $this->action->execute(1, new UserUpdateRequestDTO(['avatar_url' => null], service('validation')));
    }
}
