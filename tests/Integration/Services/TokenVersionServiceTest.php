<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\UserModel;
use App\Services\Tokens\TokenVersionService;
use Tests\Support\IntegrationTestCase;

final class TokenVersionServiceTest extends IntegrationTestCase
{
    public function testVersionIsMonotonicAndMatchesOnlyCurrentSession(): void
    {
        $userId = (int) (new UserModel())->insert([
            'email' => 'token-version-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);
        $service = new TokenVersionService(new UserModel());

        $this->assertSame(0, $service->current($userId));
        $this->assertSame(1, $service->increment($userId));
        $this->assertTrue($service->matches($userId, 1));
        $this->assertFalse($service->matches($userId, 0));
        $this->assertSame(2, $service->increment($userId));
    }
}
