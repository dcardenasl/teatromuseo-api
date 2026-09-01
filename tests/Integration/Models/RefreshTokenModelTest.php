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
            'family_id' => bin2hex(random_bytes(16)),
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
            'family_id' => bin2hex(random_bytes(16)),
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
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => 'token2',
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->model->where('user_id', $this->testUserId)->delete();

        $result = $this->model->where('user_id', $this->testUserId)->findAll();
        $this->assertEmpty($result);
    }

    public function testGetActiveTokenReturnsUnexpiredUnrevokedToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => \dcardenasl\Ci4ApiCore\Security\Hasher::token($rawToken),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $result = $this->model->getActiveToken($rawToken);

        $this->assertNotNull($result);
        $this->assertSame($this->testUserId, (int) $result->user_id);
    }

    public function testGetActiveTokenReturnsNullForExpiredToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => \dcardenasl\Ci4ApiCore\Security\Hasher::token($rawToken),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $this->assertNull($this->model->getActiveToken($rawToken));
    }

    public function testGetActiveTokenReturnsNullForRevokedToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => \dcardenasl\Ci4ApiCore\Security\Hasher::token($rawToken),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNull($this->model->getActiveToken($rawToken));
    }

    public function testDeleteExpiredRemovesOnlyExpiredTokens(): void
    {
        $expiredToken = bin2hex(random_bytes(32));
        $activeToken = bin2hex(random_bytes(32));

        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => \dcardenasl\Ci4ApiCore\Security\Hasher::token($expiredToken),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->model->insert([
            'user_id' => $this->testUserId,
            'token' => \dcardenasl\Ci4ApiCore\Security\Hasher::token($activeToken),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $deleted = $this->model->deleteExpired();

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertNull($this->model->getActiveToken($expiredToken) ?? null);
        $this->assertNotNull($this->model->where('token', \dcardenasl\Ci4ApiCore\Security\Hasher::token($activeToken))->first());
    }
}
