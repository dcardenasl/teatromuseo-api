<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Response\Users\UserResponseDTO;
use App\Entities\UserEntity;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Users\Actions\ApproveUserAction;
use App\Services\Users\Actions\CreateUserAction;
use App\Services\Users\Actions\UpdateUserAction;
use App\Services\Users\UserService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * UserService collaboration tests.
 *
 * Tests CRUD operations with mocked collaborators. Lives under the
 * Integration suite because the service's `wrapInTransaction()` opens
 * a real DB connection (transBegin) even when every other dependency
 * is mocked. The full DB-backed end-to-end suite lives in
 * UserServiceTest in this same directory.
 */
class UserServiceMockedTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected UserService $service;
    protected UserRepositoryInterface $mockUserRepository;
    protected ApproveUserAction $mockApproveUserAction;
    protected CreateUserAction $mockCreateUserAction;
    protected UpdateUserAction $mockUpdateUserAction;
    protected ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockApproveUserAction = $this->createMock(ApproveUserAction::class);
        $this->mockCreateUserAction = $this->createMock(CreateUserAction::class);
        $this->mockUpdateUserAction = $this->createMock(UpdateUserAction::class);
        $mockAuthz = $this->createMock(\App\Services\Iam\IamAuthorizationService::class);
        $this->responseMapper = new class () implements ResponseMapperInterface {
            public function map(object|array $source): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
            {
                $data = is_array($source) ? $source : $source->toArray();
                return UserResponseDTO::fromArray($data);
            }
        };

        $this->service = new UserService(
            $this->mockUserRepository,
            $this->responseMapper,
            $this->mockApproveUserAction,
            $this->mockCreateUserAction,
            $this->mockUpdateUserAction,
            $mockAuthz
        );
    }

    private function createUserEntity(array $data): UserEntity
    {
        return new UserEntity($data);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsUserData(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        $this->mockUserRepository->expects($this->once())->method('find')->with(1)->willReturn($user);

        $result = $this->service->show(1);

        $this->assertInstanceOf(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class, $result);
        $this->assertEquals('test@example.com', $result->toArray()['email']);
    }

    public function testShowWithNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserRepository->method('find')->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->service->show(999);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesUser(): void
    {
        $request = new \App\DTO\Request\Users\UserCreateRequestDTO([
            'email' => 'new@example.com',
        ], service('validation'));

        $expectedUser = $this->createUserEntity(['id' => 1, 'email' => 'new@example.com']);
        $this->mockCreateUserAction->expects($this->once())->method('execute')->willReturn($expectedUser);

        $result = $this->service->store($request);
        $this->assertInstanceOf(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class, $result);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesUser(): void
    {
        $id = 1;
        $user = $this->createUserEntity(['id' => $id, 'role' => 'user']);
        $request = new \App\DTO\Request\Users\UserUpdateRequestDTO([
            'first_name' => 'Updated',
        ], service('validation'));
        $this->mockUpdateUserAction->expects($this->once())->method('execute')->with($id, $request)->willReturn($user);

        $result = $this->service->update($id, $request);
        $this->assertInstanceOf(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class, $result);
    }

    public function testApproveDelegatesToApproveUserAction(): void
    {
        $id = 1;
        $context = new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(10);
        $approvedUser = $this->createUserEntity([
            'id' => $id,
            'email' => 'approved@example.com',
            'status' => 'active',
        ]);

        $this->mockApproveUserAction
            ->expects($this->once())
            ->method('execute')
            ->with($id, $context, 'https://client.test')
            ->willReturn($approvedUser);

        $result = $this->service->approve($id, $context, 'https://client.test');
        $this->assertInstanceOf(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface::class, $result);
        $this->assertEquals('approved@example.com', $result->toArray()['email']);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesUser(): void
    {
        $id = 1;
        $this->mockUserRepository->method('find')->willReturn($this->createUserEntity(['id' => $id, 'role' => 'user']));
        $this->mockUserRepository->expects($this->once())->method('delete')->with($id)->willReturn(true);

        $result = $this->service->destroy($id);
        $this->assertTrue($result);
    }
}
