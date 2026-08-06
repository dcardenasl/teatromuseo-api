<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\DTO\Request\Iam\ListUserPermissionsRequestDTO;
use App\DTO\Response\Iam\UserPermissionsResponseDTO;
use App\Entities\ApplicationEntity;
use App\Entities\UserEntity;
use App\Models\ApplicationModel;
use App\Models\UserModel;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Iam\UserPermissionsService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Unit tests for UserPermissionsService. The service is thin: validate that
 * unknown user/app produce NotFoundException, and that the happy path
 * delegates to EffectivePermissionsResolver and returns a well-formed DTO.
 *
 * @internal
 */
final class UserPermissionsServiceTest extends CIUnitTestCase
{
    public function testServiceIsResolvable(): void
    {
        $service = Services::userPermissionsService(false);

        $this->assertInstanceOf(UserPermissionsService::class, $service);
    }

    public function testHappyPathReturnsDtoWithResolvedPermissions(): void
    {
        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with(42, 7)
            ->willReturn(['users.read', 'users.write']);

        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(42)->willReturn(new UserEntity(['id' => 42]));

        $applicationModel = $this->createMock(ApplicationModel::class);
        $applicationModel->method('findByCode')->with('blog')
            ->willReturn(new ApplicationEntity(['id' => 7, 'code' => 'blog', 'name' => 'Blog']));

        $service = new UserPermissionsService($resolver, $userModel, $applicationModel);
        $request = new ListUserPermissionsRequestDTO(['app' => 'blog'], Services::validation(null, false));

        $result = $service->listForUser(42, $request);

        $this->assertInstanceOf(UserPermissionsResponseDTO::class, $result);
        $this->assertSame(42, $result->user_id);
        $this->assertSame(7, $result->application->id);
        $this->assertSame('blog', $result->application->code);
        $this->assertSame('Blog', $result->application->name);
        $this->assertSame(['users.read', 'users.write'], $result->permissions);
    }

    public function testUnknownUserThrowsNotFound(): void
    {
        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(999)->willReturn(null);

        $applicationModel = $this->createMock(ApplicationModel::class);
        $applicationModel->expects($this->never())->method('findByCode');

        $service = new UserPermissionsService($resolver, $userModel, $applicationModel);
        $request = new ListUserPermissionsRequestDTO(['app' => 'self'], Services::validation(null, false));

        $this->expectException(NotFoundException::class);
        $service->listForUser(999, $request);
    }

    public function testUnknownAppCodeThrowsNotFound(): void
    {
        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $userModel = $this->createMock(UserModel::class);
        $userModel->method('find')->with(1)->willReturn(new UserEntity(['id' => 1]));

        $applicationModel = $this->createMock(ApplicationModel::class);
        $applicationModel->method('findByCode')->with('ghost')->willReturn(null);

        $service = new UserPermissionsService($resolver, $userModel, $applicationModel);
        $request = new ListUserPermissionsRequestDTO(['app' => 'ghost'], Services::validation(null, false));

        $this->expectException(NotFoundException::class);
        $service->listForUser(1, $request);
    }
}
