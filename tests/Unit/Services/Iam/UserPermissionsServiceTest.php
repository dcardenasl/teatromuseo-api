<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\DTO\Request\Iam\ListUserPermissionsRequestDTO;
use App\DTO\Response\Iam\UserPermissionsResponseDTO;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Iam\UserPermissionsService;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\ConnectionInterface;
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

        $db = $this->mockConnection([
            'users' => [['id' => 42]],
            'applications' => [['id' => 7, 'code' => 'blog', 'name' => 'Blog']],
        ]);

        $service = new UserPermissionsService($resolver, $db);
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

        $db = $this->mockConnection(['users' => []]);

        $service = new UserPermissionsService($resolver, $db);
        $request = new ListUserPermissionsRequestDTO(['app' => 'self'], Services::validation(null, false));

        $this->expectException(NotFoundException::class);
        $service->listForUser(999, $request);
    }

    public function testUnknownAppCodeThrowsNotFound(): void
    {
        $resolver = $this->createMock(EffectivePermissionsResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $db = $this->mockConnection([
            'users' => [['id' => 1]],
            'applications' => [],
        ]);

        $service = new UserPermissionsService($resolver, $db);
        $request = new ListUserPermissionsRequestDTO(['app' => 'ghost'], Services::validation(null, false));

        $this->expectException(NotFoundException::class);
        $service->listForUser(1, $request);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tableRows
     */
    private function mockConnection(array $tableRows): ConnectionInterface
    {
        $db = $this->createMock(ConnectionInterface::class);

        $db->method('table')->willReturnCallback(function (string $table) use ($tableRows): BaseBuilder {
            $rows = $tableRows[$table] ?? [];
            $row = $rows[0] ?? null;

            $result = $this->createMock(BaseResult::class);
            $result->method('getRowArray')->willReturn($row);

            $builder = $this->createMock(BaseBuilder::class);
            $builder->method('select')->willReturnSelf();
            $builder->method('where')->willReturnSelf();
            $builder->method('get')->willReturn($result);

            return $builder;
        });

        return $db;
    }
}
