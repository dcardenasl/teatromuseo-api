<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\Enums\RefreshTokenRevocationReason;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\PermissionModel;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Models\UserRoleModel;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Tokens\RefreshTokenService;
use App\Services\Tokens\TokenVersionService;
use App\Services\Users\UserAccountGuard;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\IntegrationTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * RefreshTokenService Integration Tests
 *
 * `refreshAccessToken()` and `revokeAllUserTokens()` are wrapped in
 * `HandlesTransactions::wrapInTransaction()`, which connects to the
 * database directly via `Config\Database::connect()` regardless of any
 * mocked model — so exercising them genuinely needs a live DB and belongs
 * here, not in tests/Unit (see RefreshTokenServiceTest there for the
 * fully-mocked, DB-free coverage of this service).
 */
class RefreshTokenServiceTest extends IntegrationTestCase
{
    use CustomAssertionsTrait;

    protected RefreshTokenService $service;
    protected RefreshTokenModel $refreshTokenModel;
    protected UserModel $userModel;
    protected JwtServiceInterface $mockJwtService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshTokenModel = new RefreshTokenModel();
        $this->userModel = new UserModel();

        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);

        $this->service = new RefreshTokenService(
            $this->refreshTokenModel,
            $this->mockJwtService,
            $this->userModel,
            new UserAccountGuard(),
            new EffectivePermissionsResolver(new UserRoleModel(), new PermissionModel(), service('cache')),
            $this->mockAuditService,
            new TokenVersionService($this->userModel)
        );
    }

    private function createActiveUser(): int
    {
        return (int) $this->userModel->insert([
            'email' => 'refresh-integration-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rows persist across test methods within this class (IntegrationTestCase
     * only purges at class boundaries), and `token` is unique, so every raw
     * token used in this file must be unique too.
     */
    private function rawToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function testRefreshRotatesWithinTheExistingFamily(): void
    {
        $userId = $this->createActiveUser();
        $familyId = bin2hex(random_bytes(16));
        $rawToken = $this->rawToken();

        $oldTokenId = (int) $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => Hasher::token($rawToken),
            'family_id' => $familyId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $this->mockJwtService->method('encode')->willReturn('new-access-token');

        $result = $this->service->refreshAccessToken(new RefreshTokenRequestDTO([
            'refresh_token' => $rawToken,
        ], service('validation')));

        $this->assertSame('new-access-token', $result->access_token);
        $this->assertSame(64, strlen($result->refresh_token));

        $oldToken = $this->refreshTokenModel->find($oldTokenId);
        $this->assertNotNull($oldToken->revoked_at);
        $this->assertSame(RefreshTokenRevocationReason::Rotated->value, $oldToken->revoked_reason);

        $newToken = $this->refreshTokenModel->where('token', Hasher::token($result->refresh_token))->first();
        $this->assertNotNull($newToken);
        $this->assertSame($familyId, $newToken->family_id);
        $this->assertSame($oldTokenId, (int) $newToken->parent_id);
        $this->assertNull($newToken->revoked_at);
    }

    public function testReusingRotatedRefreshTokenRevokesAllSessionsAndInvalidatesAccessTokens(): void
    {
        $userId = $this->createActiveUser();
        $familyId = bin2hex(random_bytes(16));
        $rawToken = $this->rawToken();

        // The rotated (already-replaced) token being replayed.
        $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => Hasher::token($rawToken),
            'family_id' => $familyId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_reason' => RefreshTokenRevocationReason::Rotated->value,
        ]);

        // A sibling, still-active session for the same user that must also
        // be revoked once reuse is detected.
        $siblingTokenId = (int) $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => Hasher::token($this->rawToken()),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $versionBefore = (new TokenVersionService($this->userModel))->current($userId);

        $this->mockAuditService
            ->expects($this->once())
            ->method('log')
            ->with(
                'revoked_token_reuse_detected',
                'tokens',
                $userId,
                [],
                $this->callback(static function (array $data) use ($userId, $familyId): bool {
                    return $data['user_id'] === $userId && $data['family_id'] === $familyId;
                }),
                null,
                'denied',
                'critical'
            );

        $this->expectException(AuthenticationException::class);

        try {
            $this->service->refreshAccessToken(new RefreshTokenRequestDTO([
                'refresh_token' => $rawToken,
            ], service('validation')));
        } finally {
            $sibling = $this->refreshTokenModel->find($siblingTokenId);
            $this->assertNotNull($sibling->revoked_at);
            $this->assertSame(RefreshTokenRevocationReason::ReuseDetected->value, $sibling->revoked_reason);

            $versionAfter = (new TokenVersionService($this->userModel))->current($userId);
            $this->assertSame($versionBefore + 1, $versionAfter);
        }
    }

    public function testRevokeAllUserTokensCallsModel(): void
    {
        $userId = $this->createActiveUser();

        $activeTokenId = (int) $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => Hasher::token($this->rawToken()),
            'family_id' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $versionBefore = (new TokenVersionService($this->userModel))->current($userId);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertSame(\dcardenasl\Ci4ApiCore\Support\OperationState::SUCCESS, $result->state);

        $token = $this->refreshTokenModel->find($activeTokenId);
        $this->assertNotNull($token->revoked_at);
        $this->assertSame(RefreshTokenRevocationReason::RevokeAll->value, $token->revoked_reason);

        $versionAfter = (new TokenVersionService($this->userModel))->current($userId);
        $this->assertSame($versionBefore + 1, $versionAfter);
    }
}
