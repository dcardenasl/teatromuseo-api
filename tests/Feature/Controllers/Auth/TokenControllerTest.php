<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * TokenController Feature Tests
 *
 * Tests token revocation endpoints with full HTTP request/response cycle
 */
class TokenControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testRevokeWithValidTokenReturnsSuccess(): void
    {
        $email = 'revoke-test@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post('/api/v1/auth/revoke');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testRevokeWithoutTokenReturns401(): void
    {
        // Without token, JWT filter returns 401
        $result = $this->post('/api/v1/auth/revoke');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testRevokeWithInvalidTokenReturns401(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
        ])->post('/api/v1/auth/revoke');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testRevokeWithMalformedAuthHeaderReturns401(): void
    {
        // Malformed header is caught by JWT filter, returns 401
        $result = $this->withHeaders([
            'Authorization' => 'InvalidFormat some-token',
        ])->post('/api/v1/auth/revoke');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testRevokeAllWithValidTokenReturnsSuccess(): void
    {
        $email = 'revoke-all-test@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post('/api/v1/auth/revoke-all');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testRevokeAllWithoutTokenReturns401(): void
    {
        $result = $this->post('/api/v1/auth/revoke-all');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }
}
