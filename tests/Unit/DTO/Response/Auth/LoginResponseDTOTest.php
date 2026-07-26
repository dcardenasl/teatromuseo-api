<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Response\Auth;

use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\MeResponseDTO;
use CodeIgniter\Test\CIUnitTestCase;

final class LoginResponseDTOTest extends CIUnitTestCase
{
    public function testFromArrayHydratesNestedUserDto(): void
    {
        $dto = LoginResponseDTO::fromArray([
            'access_token' => 'jwt.access.token',
            'refresh_token' => 'refresh.token',
            'expires_in' => 3600,
            'user' => [
                'id' => 7,
                'email' => 'user@example.com',
                'first_name' => 'User',
                'last_name' => 'Example',
                'status' => 'active',
                'avatar_url' => null,
                'created_at' => null,
                'updated_at' => null,
                'roles' => [],
                'permissions' => ['users.read'],
            ],
        ]);

        $this->assertInstanceOf(MeResponseDTO::class, $dto->user);
        $this->assertSame('user@example.com', $dto->user->email);
        $this->assertSame(['users.read'], $dto->user->permissions);
        $this->assertSame('jwt.access.token', $dto->toArray()['access_token']);
        $this->assertSame('user@example.com', $dto->toArray()['user']['email']);
    }
}
