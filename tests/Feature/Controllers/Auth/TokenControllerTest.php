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

        // The JWT was issued before the account-wide version bump and must
        // fail immediately, without waiting for its exp claim.
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->get('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function testReusingRotatedRefreshTokenRevokesTheAccount(): void
    {
        $email = 'refresh-reuse-test@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $login = $this->withBodyFormat('json')->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $login->assertStatus(200);
        $loginPayload = json_decode($login->getJSON(), true);
        $oldAccessToken = $loginPayload['access_token'] ?? $loginPayload['data']['access_token'] ?? '';
        $oldRefreshToken = $loginPayload['refresh_token'] ?? $loginPayload['data']['refresh_token'] ?? '';

        $firstRefresh = $this->withBodyFormat('json')->post('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);
        $firstRefresh->assertStatus(200);
        $firstRefreshPayload = json_decode($firstRefresh->getJSON(), true);
        $rotatedAccessToken = $firstRefreshPayload['access_token'] ?? $firstRefreshPayload['data']['access_token'] ?? '';
        $rotatedRefreshToken = $firstRefreshPayload['refresh_token'] ?? $firstRefreshPayload['data']['refresh_token'] ?? '';

        $reuse = $this->withBodyFormat('json')->post('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);
        $reuse->assertStatus(401);

        $revokedSession = $this->withBodyFormat('json')->post('/api/v1/auth/refresh', [
            'refresh_token' => $rotatedRefreshToken,
        ]);
        $revokedSession->assertStatus(401);

        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->withHeaders(['Authorization' => "Bearer {$oldAccessToken}"])
            ->get('/api/v1/auth/me')
            ->assertStatus(401);
        $this->withHeaders(['Authorization' => "Bearer {$rotatedAccessToken}"])
            ->get('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function testRevokeAllWithoutTokenReturns401(): void
    {
        $result = $this->post('/api/v1/auth/revoke-all');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }
}
