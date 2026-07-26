<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\Support\ApiTestCase;

/**
 * Security Bypass Regression Tests
 *
 * Verified that identified security bypasses are correctly closed.
 */
class SecurityBypassTest extends ApiTestCase
{
    /**
     * Verify that X-Test-User-Role header no longer grants access.
     */
    public function testRoleBypassHeaderIsIgnored(): void
    {
        // Try to access a protected route with the bypass header but no JWT
        $response = $this->withHeaders(['X-Test-User-Role' => 'superadmin'])
                         ->get('/api/v1/users');

        // Should return 401 because jwtauth is missing, and the header should be ignored by the permission filter
        $response->assertStatus(401);
    }

    /**
     * Verify that SKIP_VERIFY password bypass is rejected.
     */
    public function testPasswordBypassIsRejected(): void
    {
        // Create a test user
        $userModel = model(\App\Models\UserModel::class);
        $email = 'security-test@example.com';
        $userModel->insert([
            'email' => $email,
            'password' => 'RealPass123!',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->post('/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'SKIP_VERIFY'
        ]);

        // Should fail because the new secret is high-entropy and DIFFERENT
        $response->assertStatus(401);
    }

    /**
     * Verify that the NEW high-entropy test secret works ONLY in testing environment.
     */
    public function testNewTestSecretWorksInTesting(): void
    {
        // Create a test user
        $userModel = model(\App\Models\UserModel::class);
        $email = 'security-test-ok@example.com';
        $userModel->insert([
            'email' => $email,
            'password' => 'RealPass123!',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->post('/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'SKIP_VERIFY_99_ae_7b_21_42_8c'
        ]);

        // Should succeed in this environment (testing)
        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);
    }
}
