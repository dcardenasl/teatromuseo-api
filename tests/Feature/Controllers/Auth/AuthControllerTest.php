<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * AuthController Feature Tests
 */
class AuthControllerTest extends ApiTestCase
{
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();

        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('authService');
    }

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('authService');
        parent::tearDown();
    }

    // ==================== REGISTER ENDPOINT TESTS ====================

    public function testRegisterWithValidDataReturnsCreated(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'new@example.com',
                'password' => 'ValidPass123!',
                'first_name' => 'New',
                'last_name' => 'User',
            ]);

        // Returns 201 Created
        $result->assertStatus(201);

        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('id', $json['data']);
    }

    public function testRegisterWithInvalidDataReturns422(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'invalid-email',
                'password' => 'weak',
            ]);

        $result->assertStatus(422);
    }

    public function testRegisterWithDuplicateEmailReturnsError(): void
    {
        $email = 'existing@example.com';
        $this->userModel->insert([
            'email' => $email,
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => $email,
                'password' => 'ValidPass123!',
                'first_name' => 'Name',
                'last_name' => 'Last',
            ]);

        $result->assertStatus(422);
        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('email', $json['errors']);
    }

    // ==================== LOGIN ENDPOINT TESTS ====================

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $this->userModel->insert([
            'email' => 'login@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'login@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('access_token', $json['data']);

        // Contract: the canonical "me" shape is embedded under `user` and
        // MUST include `permissions` (effective permission codes used for
        // UI gating on the consumer). Drop this field and frontends lose
        // their authorization context.
        $this->assertArrayHasKey('user', $json['data']);
        $this->assertArrayHasKey('permissions', $json['data']['user']);
        $this->assertIsArray($json['data']['user']['permissions']);
        $this->assertArrayHasKey('roles', $json['data']['user']);
        $this->assertArrayHasKey('status', $json['data']['user']);
    }

    public function testLoginWithEmptyCredentialsReturns422(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => '',
                'password' => '',
            ]);

        $result->assertStatus(422);
    }

    public function testGoogleLoginCreatesPendingUserAndReturns202(): void
    {
        $this->injectGoogleIdentityMock([
            'provider' => 'google',
            'provider_id' => 'google-sub-new',
            'email' => 'google-new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'avatar_url' => null,
            'claims' => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $result->assertStatus(202);
        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('user', $json['data']);
    }

    // ==================== HELPERS ====================

    private function injectGoogleIdentityMock(array $identityData): void
    {
        $mock = $this->createMock(GoogleIdentityServiceInterface::class);
        $mock->method('verifyIdToken')->willReturn(
            \App\DTO\Response\Identity\GoogleIdentityResponseDTO::fromArray($identityData)
        );
        \Config\Services::injectMock('googleIdentityService', $mock);
    }

    private function injectEmailServiceMock(): void
    {
        $mock = $this->createMock(EmailServiceInterface::class);
        $mock->method('queueTemplate')->willReturn(1);
        \Config\Services::injectMock('emailService', $mock);
    }
}
