<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\Entities\UserEntity;
use App\Enums\RefreshTokenRevocationReason;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\Tokens\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * RefreshTokenService Unit Tests
 *
 * Tests token lifecycle with mocked dependencies.
 */
class RefreshTokenServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    private const VALID_REFRESH_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const UNKNOWN_REFRESH_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected RefreshTokenService $service;
    protected RefreshTokenModel $mockRefreshTokenModel;
    protected JwtServiceInterface $mockJwtService;
    protected UserModel $mockUserModel;
    protected \App\Services\Users\UserAccountGuard $mockUserAccountGuard;
    protected \App\Services\Iam\EffectivePermissionsResolver $mockPermissionsResolver;
    protected AuditServiceInterface $mockAuditService;
    protected \App\Interfaces\Tokens\TokenVersionServiceInterface $mockTokenVersionService;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('JWT_REFRESH_TOKEN_TTL=604800');

        $this->mockRefreshTokenModel = $this->createMock(RefreshTokenModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockUserModel = $this->createMock(UserModel::class);

        $this->mockUserAccountGuard = $this->createMock(\App\Services\Users\UserAccountGuard::class);
        $this->mockPermissionsResolver = $this->createMock(\App\Services\Iam\EffectivePermissionsResolver::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->mockTokenVersionService = $this->createMock(\App\Interfaces\Tokens\TokenVersionServiceInterface::class);

        $this->service = new RefreshTokenService(
            $this->mockRefreshTokenModel,
            $this->mockJwtService,
            $this->mockUserModel,
            $this->mockUserAccountGuard,
            $this->mockPermissionsResolver,
            $this->mockAuditService,
            $this->mockTokenVersionService
        );
    }

    protected function tearDown(): void
    {
        putenv('JWT_REFRESH_TOKEN_TTL');
        parent::tearDown();
    }

    // ==================== ISSUE REFRESH TOKEN TESTS ====================

    public function testIssueRefreshTokenReturnsTokenString(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return isset($data['user_id'])
                    && $data['user_id'] === 1
                    && isset($data['token'])
                    && strlen($data['token']) === 64  // 32 bytes = 64 hex chars
                    && isset($data['expires_at']);
            }))
            ->willReturn(1);

        $token = $this->service->issueRefreshToken(1);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testIssueRefreshTokenGeneratesUniqueTokens(): void
    {
        $this->mockRefreshTokenModel
            ->method('insert')
            ->willReturn(1);

        $token1 = $this->service->issueRefreshToken(1);
        $token2 = $this->service->issueRefreshToken(1);

        $this->assertNotEquals($token1, $token2);
    }

    // ==================== REVOKE TESTS ====================

    public function testRefreshRotatesWithinTheExistingFamily(): void
    {
        $familyId = str_repeat('a', 32);
        $record = (object) [
            'id' => 12,
            'user_id' => 1,
            'family_id' => $familyId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'revoked_at' => null,
            'revoked_reason' => null,
        ];
        $user = new UserEntity([
            'id' => 1,
            'email' => 'refresh@example.com',
            'status' => 'active',
            'auth_token_version' => 3,
        ]);

        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('findForUpdate')
            ->with(self::VALID_REFRESH_TOKEN)
            ->willReturn($record);
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeToken')
            ->with(self::VALID_REFRESH_TOKEN, RefreshTokenRevocationReason::Rotated)
            ->willReturn(true);
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static function (array $data) use ($familyId): bool {
                return $data['user_id'] === 1
                    && $data['family_id'] === $familyId
                    && $data['parent_id'] === 12;
            }))
            ->willReturn(13);
        $this->mockUserModel->method('find')->willReturn($user);
        $this->mockPermissionsResolver->method('resolveAll')->willReturn([]);
        $this->mockJwtService->method('encode')->willReturn('new-access-token');

        $result = $this->service->refreshAccessToken(new RefreshTokenRequestDTO([
            'refresh_token' => self::VALID_REFRESH_TOKEN,
        ], service('validation')));

        $this->assertSame('new-access-token', $result->access_token);
        $this->assertSame(64, strlen($result->refresh_token));
    }

    public function testReusingRotatedRefreshTokenRevokesAllSessionsAndInvalidatesAccessTokens(): void
    {
        $familyId = str_repeat('b', 32);
        $record = (object) [
            'id' => 21,
            'user_id' => 7,
            'family_id' => $familyId,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_reason' => RefreshTokenRevocationReason::Rotated->value,
        ];

        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('findForUpdate')
            ->with(self::VALID_REFRESH_TOKEN)
            ->willReturn($record);
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with(7, RefreshTokenRevocationReason::ReuseDetected);
        $this->mockTokenVersionService
            ->expects($this->once())
            ->method('increment')
            ->with(7)
            ->willReturn(4);
        $this->mockAuditService
            ->expects($this->once())
            ->method('log')
            ->with(
                'revoked_token_reuse_detected',
                'tokens',
                7,
                [],
                $this->callback(static function (array $data) use ($familyId): bool {
                    return $data['user_id'] === 7 && $data['family_id'] === $familyId;
                }),
                null,
                'denied',
                'critical'
            );

        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException::class);

        $this->service->refreshAccessToken(new RefreshTokenRequestDTO([
            'refresh_token' => self::VALID_REFRESH_TOKEN,
        ], service('validation')));
    }

    public function testRevokeWithValidTokenReturnsSuccess(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeToken')
            ->with(self::VALID_REFRESH_TOKEN, RefreshTokenRevocationReason::Logout)
            ->willReturn(true);

        $result = $this->service->revoke(new RefreshTokenRequestDTO([
            'refresh_token' => self::VALID_REFRESH_TOKEN,
        ], service('validation')));

        $this->assertSame(\dcardenasl\Ci4ApiCore\Support\OperationState::SUCCESS, $result->state);
    }

    public function testRevokeWithNonExistentTokenThrowsNotFoundException(): void
    {
        $this->mockRefreshTokenModel
            ->method('revokeToken')
            ->willReturn(false);

        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\NotFoundException::class);

        $this->service->revoke(new RefreshTokenRequestDTO([
            'refresh_token' => self::UNKNOWN_REFRESH_TOKEN,
        ], service('validation')));
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensCallsModel(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with(1, RefreshTokenRevocationReason::RevokeAll);

        $this->mockTokenVersionService
            ->expects($this->once())
            ->method('increment')
            ->with(1)
            ->willReturn(1);

        $result = $this->service->revokeAllUserTokens(1);

        $this->assertSame(\dcardenasl\Ci4ApiCore\Support\OperationState::SUCCESS, $result->state);
    }
}
