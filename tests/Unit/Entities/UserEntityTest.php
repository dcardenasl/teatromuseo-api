<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\UserEntity;
use CodeIgniter\Test\CIUnitTestCase;

final class UserEntityTest extends CIUnitTestCase
{
    public function testToArrayRemovesSensitiveFields(): void
    {
        $entity = new UserEntity([
            'email' => 'user@example.com',
            'password' => 'hashed-value',
            'email_verification_token' => 'token123',
            'verification_token_expires' => '2026-03-04 00:00:00',
            'oauth_provider_id' => 'provider-uid',
            'first_name' => 'Alice',
        ]);

        $payload = $entity->toArray();

        $this->assertArrayHasKey('email', $payload);
        $this->assertArrayHasKey('first_name', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('email_verification_token', $payload);
        $this->assertArrayNotHasKey('verification_token_expires', $payload);
        $this->assertArrayNotHasKey('oauth_provider_id', $payload);
    }
}
