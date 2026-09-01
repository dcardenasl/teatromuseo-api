<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\RefreshTokenRevocationReason;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\TokenVersionServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Models\UserModel;
use App\Services\Tokens\BearerTokenService;
use App\Services\Tokens\TokenRevocationService;
use CodeIgniter\Cache\CacheInterface;
use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\IntegrationTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * TokenRevocationService Integration Tests
 *
 * `revokeAllUserTokens()` is wrapped in
 * `HandlesTransactions::wrapInTransaction()`, which connects to the
 * database directly via `Config\Database::connect()` regardless of any
 * mocked model — so exercising it genuinely needs a live DB and belongs
 * here, not in tests/Unit (see TokenRevocationServiceTest there for the
 * fully-mocked, DB-free coverage of this service).
 */
class TokenRevocationServiceTest extends IntegrationTestCase
{
    use CustomAssertionsTrait;

    protected TokenRevocationService $service;
    protected RefreshTokenModel $refreshTokenModel;
    protected UserModel $userModel;
    protected TokenVersionServiceInterface $mockTokenVersionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshTokenModel = new RefreshTokenModel();
        $this->userModel = new UserModel();
        $this->mockTokenVersionService = $this->createMock(TokenVersionServiceInterface::class);

        $this->service = new TokenRevocationService(
            $this->createMock(TokenBlacklistModel::class),
            $this->refreshTokenModel,
            $this->createMock(JwtServiceInterface::class),
            $this->createMock(AuditServiceInterface::class),
            $this->createMock(CacheInterface::class),
            new BearerTokenService(),
            $this->mockTokenVersionService,
            3600
        );
    }

    public function testRevokeAllUserTokensCallsModel(): void
    {
        $userId = (int) $this->userModel->insert([
            'email' => 'revoke-all-integration-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $activeTokenId = (int) $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => Hasher::token(bin2hex(random_bytes(32))),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $this->mockTokenVersionService
            ->expects($this->once())
            ->method('increment')
            ->with($userId)
            ->willReturn(1);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertTrue($result);

        $token = $this->refreshTokenModel->find($activeTokenId);
        $this->assertNotNull($token->revoked_at);
        $this->assertSame(RefreshTokenRevocationReason::RevokeAll->value, $token->revoked_reason);
    }
}
