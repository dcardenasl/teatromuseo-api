<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Request\Iam;

use App\DTO\Request\Iam\PermissionCreateRequestDTO;
use App\DTO\Request\Iam\PermissionUpdateRequestDTO;
use App\DTO\Request\Iam\RoleIndexRequestDTO;
use App\DTO\Request\Iam\SelfPermissionsRequestDTO;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * @internal
 */
final class PermissionRequestDTOsTest extends CIUnitTestCase
{
    // ==================== PermissionCreateRequestDTO ====================

    public function testPermissionCreateMapsAllFields(): void
    {
        $dto = new PermissionCreateRequestDTO([
            'application_id' => 3,
            'code' => 'users.write',
            'resource' => 'users',
            'action' => 'write',
            'description' => 'Allows writing users',
        ], service('validation'));

        $this->assertSame([
            'application_id' => 3,
            'code' => 'users.write',
            'resource' => 'users',
            'action' => 'write',
            'description' => 'Allows writing users',
        ], $dto->toArray());
    }

    public function testPermissionCreateDefaultsOptionalFields(): void
    {
        $dto = new PermissionCreateRequestDTO([
            'code' => 'users.write',
            'resource' => 'users',
            'action' => 'write',
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame(0, $data['application_id']);
        $this->assertSame('', $data['description']);
    }

    public function testPermissionCreateThrowsWhenCodeMissing(): void
    {
        $this->expectException(ValidationException::class);

        new PermissionCreateRequestDTO([
            'resource' => 'users',
            'action' => 'write',
        ], service('validation'));
    }

    public function testPermissionCreateThrowsWhenCodeTooLong(): void
    {
        $this->expectException(ValidationException::class);

        new PermissionCreateRequestDTO([
            'code' => str_repeat('a', 101),
            'resource' => 'users',
            'action' => 'write',
        ], service('validation'));
    }

    // ==================== PermissionUpdateRequestDTO ====================

    public function testPermissionUpdateOnlyIncludesProvidedFields(): void
    {
        $dto = new PermissionUpdateRequestDTO([
            'code' => 'users.read',
        ], service('validation'));

        $this->assertSame(['code' => 'users.read'], $dto->toArray());
    }

    public function testPermissionUpdateWithNoFieldsMapsToEmptyArray(): void
    {
        $dto = new PermissionUpdateRequestDTO([], service('validation'));

        $this->assertSame([], $dto->toArray());
    }

    public function testPermissionUpdateExplicitNullDescriptionClearsIt(): void
    {
        $dto = new PermissionUpdateRequestDTO([
            'description' => null,
        ], service('validation'));

        // Explicit null on the nullable column must survive to toArray(),
        // not be silently dropped — that's the bug this DTO's map() fixes.
        $this->assertArrayHasKey('description', $dto->toArray());
        $this->assertNull($dto->toArray()['description']);
    }

    public function testPermissionUpdateNullApplicationIdIsTreatedAsOmitted(): void
    {
        $dto = new PermissionUpdateRequestDTO([
            'application_id' => null,
            'code' => 'users.read',
        ], service('validation'));

        $this->assertArrayNotHasKey('application_id', $dto->toArray());
    }

    public function testPermissionUpdateThrowsWhenCodeTooLong(): void
    {
        $this->expectException(ValidationException::class);

        new PermissionUpdateRequestDTO([
            'code' => str_repeat('a', 101),
        ], service('validation'));
    }

    // ==================== RoleIndexRequestDTO ====================

    public function testRoleIndexDefaultsPageAndPerPage(): void
    {
        $dto = new RoleIndexRequestDTO([], service('validation'));

        $data = $dto->toArray();
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['per_page']);
        $this->assertNull($data['search']);
        $this->assertSame('', $data['sort']);
    }

    public function testRoleIndexMapsProvidedFields(): void
    {
        $dto = new RoleIndexRequestDTO([
            'page' => 2,
            'per_page' => 50,
            'search' => 'admin',
            'sort' => 'name',
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame(2, $data['page']);
        $this->assertSame(50, $data['per_page']);
        $this->assertSame('admin', $data['search']);
        $this->assertSame('name', $data['sort']);
    }

    public function testRoleIndexThrowsWhenPerPageExceedsLimit(): void
    {
        $this->expectException(ValidationException::class);

        new RoleIndexRequestDTO(['per_page' => 101], service('validation'));
    }

    public function testRoleIndexThrowsWhenPageIsZero(): void
    {
        $this->expectException(ValidationException::class);

        new RoleIndexRequestDTO(['page' => 0], service('validation'));
    }

    // ==================== SelfPermissionsRequestDTO ====================

    public function testSelfPermissionsMapsPermissionsArray(): void
    {
        $dto = new SelfPermissionsRequestDTO([
            'permissions' => [
                ['code' => 'users.read'],
                ['code' => 'users.write'],
            ],
        ], service('validation'));

        $this->assertSame([
            ['code' => 'users.read'],
            ['code' => 'users.write'],
        ], $dto->toArray()['permissions']);
    }

    public function testSelfPermissionsNonArrayValueMapsToNull(): void
    {
        // 'required' only checks presence, not type — a non-array value
        // passes validation but map() must still coerce it to null.
        $dto = new SelfPermissionsRequestDTO([
            'permissions' => 'not-an-array',
        ], service('validation'));

        $this->assertNull($dto->toArray()['permissions']);
    }

    public function testSelfPermissionsThrowsWhenMissing(): void
    {
        $this->expectException(ValidationException::class);

        new SelfPermissionsRequestDTO([], service('validation'));
    }
}
