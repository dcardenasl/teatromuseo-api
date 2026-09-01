<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Request;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\DTO\Request\Files\FileBulkActionRequestDTO;
use App\DTO\Request\Identity\ResetPasswordRequestDTO;
use App\DTO\Request\Users\UserCreateRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * @internal
 */
final class UsersAndApiKeysRequestDTOsTest extends CIUnitTestCase
{
    // ==================== UserCreateRequestDTO ====================

    public function testUserCreateMapsAllFieldsAndNormalizesRoleIds(): void
    {
        $dto = new UserCreateRequestDTO([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'oauth_provider' => 'google',
            'oauth_provider_id' => 'g-123',
            'avatar_url' => 'https://example.com/a.jpg',
            'locale' => 'ES',
            'role_ids' => ['3', 3, 'not-numeric', -1, 0, 5],
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame('new@example.com', $data['email']);
        $this->assertSame('es', $data['locale']);
        $this->assertSame([3, 5], $data['role_ids']);
    }

    public function testUserCreateDefaultsOptionalFieldsToNull(): void
    {
        $dto = new UserCreateRequestDTO(['email' => 'minimal@example.com'], service('validation'));

        $data = $dto->toArray();
        $this->assertNull($data['first_name']);
        $this->assertNull($data['locale']);
        $this->assertSame([], $data['role_ids']);
    }

    public function testUserCreateThrowsWhenEmailMissing(): void
    {
        $this->expectException(ValidationException::class);

        new UserCreateRequestDTO([], service('validation'));
    }

    public function testUserCreateThrowsWhenOauthProviderInvalid(): void
    {
        $this->expectException(ValidationException::class);

        new UserCreateRequestDTO([
            'email' => 'x@example.com',
            'oauth_provider' => 'facebook',
        ], service('validation'));
    }

    // ==================== UserUpdateRequestDTO ====================

    public function testUserUpdateOnlyIncludesProvidedFields(): void
    {
        $dto = new UserUpdateRequestDTO(['first_name' => 'Updated'], service('validation'));

        $this->assertSame(['first_name' => 'Updated'], $dto->toArray());
    }

    public function testUserUpdateExplicitNullAvatarUrlClearsIt(): void
    {
        $dto = new UserUpdateRequestDTO(['avatar_url' => null], service('validation'));

        $this->assertArrayHasKey('avatar_url', $dto->toArray());
        $this->assertNull($dto->toArray()['avatar_url']);
    }

    public function testUserUpdateExcludesPasswordAndRoleIdsFromToArray(): void
    {
        $dto = new UserUpdateRequestDTO([
            'password' => 'ValidPass123!',
            'role_ids' => [1, 2],
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('role_ids', $data);
        // But the properties themselves remain accessible for direct reads.
        $this->assertSame('ValidPass123!', $dto->password);
        $this->assertSame([1, 2], $dto->role_ids);
    }

    public function testUserUpdateThrowsWhenPasswordIsWeak(): void
    {
        $this->expectException(ValidationException::class);

        new UserUpdateRequestDTO(['password' => 'weak'], service('validation'));
    }

    // ==================== ApiKeyCreateRequestDTO ====================

    public function testApiKeyCreateMapsProvidedFields(): void
    {
        $dto = new ApiKeyCreateRequestDTO([
            'name' => ' My Key ',
            'rate_limit_requests' => 100,
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame('My Key', $data['name']);
        $this->assertSame(100, $data['rate_limit_requests']);
        $this->assertArrayNotHasKey('rate_limit_window', $data);
    }

    public function testApiKeyCreateThrowsWhenNameMissing(): void
    {
        $this->expectException(ValidationException::class);

        new ApiKeyCreateRequestDTO([], service('validation'));
    }

    // ==================== ApiKeyUpdateRequestDTO ====================

    public function testApiKeyUpdateMapsIsActiveAsInteger(): void
    {
        $dto = new ApiKeyUpdateRequestDTO(['is_active' => '1'], service('validation'));

        $this->assertSame(1, $dto->toArray()['is_active']);
    }

    public function testApiKeyUpdateWithNoFieldsMapsToEmptyArray(): void
    {
        $dto = new ApiKeyUpdateRequestDTO([], service('validation'));

        $this->assertSame([], $dto->toArray());
    }

    public function testApiKeyUpdateThrowsWhenIsActiveOutOfRange(): void
    {
        $this->expectException(ValidationException::class);

        new ApiKeyUpdateRequestDTO(['is_active' => 5], service('validation'));
    }

    // ==================== ResetPasswordRequestDTO ====================

    public function testResetPasswordMapsAndNormalizesEmail(): void
    {
        $dto = new ResetPasswordRequestDTO([
            'email' => 'User@Example.com',
            'token' => 'sometoken',
            'password' => 'ValidPass123!',
        ], service('validation'));

        $this->assertSame('user@example.com', $dto->toArray()['email']);
        $this->assertArrayNotHasKey('locale', $dto->toArray());
    }

    public function testResetPasswordThrowsWhenPasswordIsWeak(): void
    {
        $this->expectException(ValidationException::class);

        new ResetPasswordRequestDTO([
            'email' => 'user@example.com',
            'token' => 'sometoken',
            'password' => 'weak',
        ], service('validation'));
    }

    public function testResetPasswordThrowsWhenTokenMissing(): void
    {
        $this->expectException(ValidationException::class);

        new ResetPasswordRequestDTO([
            'email' => 'user@example.com',
            'password' => 'ValidPass123!',
        ], service('validation'));
    }

    // ==================== FileBulkActionRequestDTO ====================

    public function testFileBulkActionNormalizesAndDedupesIds(): void
    {
        $dto = new FileBulkActionRequestDTO([
            'ids' => ['3', 3, '5', 'not-numeric', -1, 0],
            'user_id' => 9,
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame([3, 5], $data['ids']);
        $this->assertSame(9, $data['user_id']);
    }

    public function testFileBulkActionThrowsWhenIdsMissing(): void
    {
        $this->expectException(ValidationException::class);

        new FileBulkActionRequestDTO(['user_id' => 1], service('validation'));
    }

    public function testFileBulkActionThrowsWhenIdsAreAllInvalid(): void
    {
        $this->expectException(ValidationException::class);

        new FileBulkActionRequestDTO(['ids' => ['not-numeric', -1, 0]], service('validation'));
    }

    public function testFileBulkActionThrowsWhenIdsIsNotAnArray(): void
    {
        $this->expectException(ValidationException::class);

        new FileBulkActionRequestDTO(['ids' => 'not-an-array'], service('validation'));
    }
}
