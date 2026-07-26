<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\PasswordResetModel;
use Tests\Support\IntegrationTestCase;

/**
 * PasswordResetModel Integration Tests
 */
class PasswordResetModelTest extends IntegrationTestCase
{
    protected PasswordResetModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PasswordResetModel();
    }

    public function testInsertCreatesPasswordReset(): void
    {
        helper('security');
        $data = [
            'email' => 'test@example.com',
            'token' => hash_token(bin2hex(random_bytes(32))),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->model->insert($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCleanExpiredRemovesOldTokens(): void
    {
        helper('security');
        // Insert old token
        $this->model->insert([
            'email' => 'old@example.com',
            'token' => hash_token('old-token'),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        // Insert fresh token
        $this->model->insert([
            'email' => 'fresh@example.com',
            'token' => hash_token('fresh-token'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->model->cleanExpired(60); // 60 minute expiry

        // Fresh token should remain
        $result = $this->model->where('email', 'fresh@example.com')->first();
        $this->assertNotNull($result);

        // Old token should be removed
        $result = $this->model->where('email', 'old@example.com')->first();
        $this->assertNull($result);
    }

    public function testIsValidTokenReturnsTrueForValidToken(): void
    {
        helper('security');
        $token = bin2hex(random_bytes(32));
        $this->model->insert([
            'email' => 'test@example.com',
            'token' => hash_token($token),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $isValid = $this->model->isValidToken('test@example.com', $token, 60);

        $this->assertTrue($isValid);
    }

    public function testIsValidTokenReturnsFalseForExpiredToken(): void
    {
        helper('security');
        $token = bin2hex(random_bytes(32));
        $this->model->insert([
            'email' => 'test@example.com',
            'token' => hash_token($token),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        $isValid = $this->model->isValidToken('test@example.com', $token, 60);

        $this->assertFalse($isValid);
    }
}
