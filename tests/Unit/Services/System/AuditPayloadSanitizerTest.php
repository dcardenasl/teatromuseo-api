<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System;

use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditPayloadSanitizer;

final class AuditPayloadSanitizerTest extends CIUnitTestCase
{
    public function testSanitizeRemovesSensitiveFieldsRecursively(): void
    {
        $sanitizer = new AuditPayloadSanitizer();

        $input = [
            'email' => 'user@example.com',
            'password' => 'secret',
            'profile' => [
                'token' => 'abc',
                'timezone' => 'UTC',
                'nested' => [
                    'refresh_token' => 'r1',
                    'enabled' => true,
                ],
            ],
        ];

        $result = $sanitizer->sanitize($input);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('token', $result['profile']);
        $this->assertArrayNotHasKey('refresh_token', $result['profile']['nested']);
        $this->assertSame('UTC', $result['profile']['timezone']);
        $this->assertTrue($result['profile']['nested']['enabled']);
    }

    public function testSanitizeRemovesTokenAndSecretPatterns(): void
    {
        $sanitizer = new AuditPayloadSanitizer();

        $input = [
            'email_verification_token' => 'abc123',
            'client_secret' => 'secret-value',
            'public_value' => 'safe',
            'nested' => [
                'auth_token_value' => 'tkn',
                'api_key_hash' => 'hash',
                'note' => 'keep',
            ],
        ];

        $result = $sanitizer->sanitize($input);

        $this->assertArrayNotHasKey('email_verification_token', $result);
        $this->assertArrayNotHasKey('client_secret', $result);
        $this->assertArrayHasKey('public_value', $result);
        $this->assertArrayNotHasKey('auth_token_value', $result['nested']);
        $this->assertArrayNotHasKey('api_key_hash', $result['nested']);
        $this->assertSame('keep', $result['nested']['note']);
    }
}
