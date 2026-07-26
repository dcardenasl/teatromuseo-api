<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\TokenBlacklistModel;
use Tests\Support\IntegrationTestCase;

/**
 * TokenBlacklistModel Integration Tests
 */
class TokenBlacklistModelTest extends IntegrationTestCase
{
    protected TokenBlacklistModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new TokenBlacklistModel();
    }

    public function testAddToBlacklistCreatesRecord(): void
    {
        $jti = 'test-jti-123';
        $expiresAt = strtotime('+1 hour');

        $result = $this->model->addToBlacklist($jti, $expiresAt);

        $this->assertTrue($result);
    }

    public function testIsBlacklistedReturnsTrueForBlacklistedToken(): void
    {
        $jti = 'blacklisted-jti';
        $this->model->addToBlacklist($jti, strtotime('+1 hour'));

        $isBlacklisted = $this->model->isBlacklisted($jti);

        $this->assertTrue($isBlacklisted);
    }

    public function testIsBlacklistedReturnsFalseForExpiredToken(): void
    {
        $jti = 'expired-jti';
        $this->model->addToBlacklist($jti, strtotime('-1 hour'));

        $isBlacklisted = $this->model->isBlacklisted($jti);

        $this->assertFalse($isBlacklisted);
    }

    public function testDeleteExpiredRemovesOldTokens(): void
    {
        // Insert expired token
        $this->model->insert([
            'token_jti' => 'expired-jti',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Insert valid token
        $this->model->insert([
            'token_jti' => 'valid-jti',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $deletedCount = $this->model->deleteExpired();

        $this->assertGreaterThan(0, $deletedCount);

        // Valid should remain
        $valid = $this->model->where('token_jti', 'valid-jti')->first();
        $this->assertNotNull($valid);
    }
}
