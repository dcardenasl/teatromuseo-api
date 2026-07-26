<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Identity;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * VerificationController Feature Tests
 */
class VerificationControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    public function testVerifyEmailReturns200Successfully(): void
    {
        $token = bin2hex(random_bytes(32));
        $email = 'test-final-verify+' . uniqid('', true) . '@example.com';

        $insertedId = $this->userModel
            ->skipValidation(true)
            ->insert([
            'email' => $email,
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => null,
            'email_verification_token' => hash_token($token),
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        $this->assertIsInt($insertedId);
        $this->assertGreaterThan(0, $insertedId);

        $result = $this->withBodyFormat('json')->post('/api/v1/auth/verify-email', [
            'token' => $token,
        ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testVerifyEmailReturns404ForInvalidToken(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/auth/verify-email', [
            'token' => 'token-that-does-not-exist-in-db-and-is-long-enough-xyz',
        ]);

        $result->assertStatus(404);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testVerifyEmailReturns422ForTooShortToken(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/auth/verify-email', [
            'token' => 'short',
        ]);

        $result->assertStatus(422);
    }

    public function testResendVerificationWithValidTokenReturnsSuccess(): void
    {
        $auth = $this->actAs('user', ['verified' => true]);
        $this->userModel->update($auth['user_id'], ['email_verified_at' => null]);

        $result = $this->post('/api/v1/auth/resend-verification');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testResendVerificationWithoutTokenReturns401(): void
    {
        $result = $this->post('/api/v1/auth/resend-verification');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }
}
