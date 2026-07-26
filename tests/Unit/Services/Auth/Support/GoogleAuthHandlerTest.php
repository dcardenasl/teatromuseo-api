<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth\Support;

use App\Entities\UserEntity;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Support\GoogleAuthHandler;
use CodeIgniter\Test\CIUnitTestCase;

class GoogleAuthHandlerTest extends CIUnitTestCase
{
    protected $mockUserRepository;
    protected $mockRefreshTokenService;
    protected GoogleAuthHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $userRoleAssignmentService = $this->createMock(\App\Services\Iam\UserRoleAssignmentService::class);

        $this->handler = new GoogleAuthHandler(
            $this->mockUserRepository,
            $this->mockRefreshTokenService,
            $userRoleAssignmentService
        );
    }

    public function testSyncProfileIfEmptyWithNoIdentityDataDoesNotUpdate(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'first_name' => '', // empty
        ]);

        $this->mockUserRepository->method('find')->willReturn($user);

        // Ensure update is NEVER called when identity data results in empty after array_filter
        $this->mockUserRepository->expects($this->never())->method('update');

        $identity = [
            'first_name' => null, // or missing
        ];

        $this->handler->syncProfileIfEmpty(1, $identity);
    }

    public function testSyncProfileIfEmptyWithDataDoesUpdate(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'first_name' => '', // empty
        ]);

        $this->mockUserRepository->method('find')->willReturn($user);

        $this->mockUserRepository->expects($this->once())
            ->method('update')
            ->with(1, ['first_name' => 'John']);

        $identity = [
            'first_name' => 'John',
        ];

        $this->handler->syncProfileIfEmpty(1, $identity);
    }
}
