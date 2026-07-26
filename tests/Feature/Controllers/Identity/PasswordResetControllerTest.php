<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Identity;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * PasswordResetController Feature Tests
 */
class PasswordResetControllerTest extends ApiTestCase
{
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== SEND RESET LINK TESTS ====================

    public function testSendResetLinkReturns200ForValidEmail(): void
    {
        $this->userModel->insert([
            'email' => 'test-pwd-final@example.com',
            'password' => password_hash('OldPass123!', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/forgot-password', [
                'email' => 'test-pwd-final@example.com',
            ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testSendResetLinkReactivatesSoftDeletedUserAsPendingApproval(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'deleted-pwd-final@example.com',
            'password' => password_hash('OldPass123!', PASSWORD_BCRYPT),
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => 99,
        ]);

        $this->userModel->delete($userId);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/forgot-password', [
                'email' => 'deleted-pwd-final@example.com',
            ]);

        $result->assertStatus(200);

        $reactivatedUser = $this->userModel->withDeleted()->find($userId);
        $this->assertEquals('pending_approval', $reactivatedUser->status);
        $this->assertNull($reactivatedUser->deleted_at);
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenReturns200ForValidToken(): void
    {
        $email = 'validate-pwd-final@example.com';
        $this->userModel->insert([
            'email' => $email,
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
        ]);

        $token = bin2hex(random_bytes(16));
        $db = \Config\Database::connect();
        $db->table('password_resets')->insert([
            'email' => $email,
            'token' => hash_token($token),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get("/api/v1/auth/validate-reset-token?token={$token}&email={$email}");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertTrue($json['data']['success']);
    }

    public function testValidateTokenReturns404ForInvalidToken(): void
    {
        $result = $this->get("/api/v1/auth/validate-reset-token?token=nonexistent-token-long-enough&email=none@example.com");

        $result->assertStatus(404);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    // ==================== RESET PASSWORD TESTS ====================

    public function testResetPasswordReturns200Successfully(): void
    {
        $email = 'reset-success-pwd-final@example.com';
        $this->userModel->insert([
            'email' => $email,
            'password' => password_hash('OldPass123!', PASSWORD_BCRYPT),
        ]);

        $token = bin2hex(random_bytes(16));
        $db = \Config\Database::connect();
        $db->table('password_resets')->insert([
            'email' => $email,
            'token' => hash_token($token),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/reset-password', [
                'email' => $email,
                'token' => $token,
                'password' => 'NewSecure123!',
            ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }
}
