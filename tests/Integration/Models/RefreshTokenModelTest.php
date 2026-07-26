<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use Tests\Support\IntegrationTestCase;

/**
 * RefreshTokenModel Integration Tests
 */
class RefreshTokenModelTest extends IntegrationTestCase
{
    protected RefreshTokenModel $model;
    protected UserModel $userModel;
    protected int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RefreshTokenModel();
        $this->userModel = new UserModel();

        // Unique email per setUp: tables are not purged between methods, only at class boundary.
        $this->testUserId = (int) $this->userModel->insert([
            'email' => 'refreshtoken-' . uniqid() . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
        ]);
    }

    public function testInsertCreatesRefreshToken(): void
    {
        $data = [
            'user_id' => $this->testUserId,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ];

        $id = $this->model->insert($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindByTokenReturnsToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $result = $this->model->where('token', $token)->first();

        $this->assertNotNull($result);
        $this->assertEquals($this->testUserId, $result->user_id);
    }

    public function testDeleteByUserIdRemovesTokens(): void
    {
        // Insert tokens
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => 'token1',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => 'token2',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->where('user_id', $this->testUserId)->delete();

        $result = $this->model->where('user_id', $this->testUserId)->findAll();
        $this->assertEmpty($result);
    }
}
