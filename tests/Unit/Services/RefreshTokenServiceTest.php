<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\Tokens\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;
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

    protected function setUp(): void
    {
        parent::setUp();

        putenv('JWT_REFRESH_TOKEN_TTL=604800');

        $this->mockRefreshTokenModel = $this->createMock(RefreshTokenModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockUserModel = $this->createMock(UserModel::class);

        $this->mockUserAccountGuard = $this->createMock(\App\Services\Users\UserAccountGuard::class);
        $this->mockPermissionsResolver = $this->createMock(\App\Services\Iam\EffectivePermissionsResolver::class);

        $this->service = new RefreshTokenService(
            $this->mockRefreshTokenModel,
            $this->mockJwtService,
            $this->mockUserModel,
            $this->mockUserAccountGuard,
            $this->mockPermissionsResolver
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

    public function testRevokeWithValidTokenReturnsSuccess(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeToken')
            ->with(self::VALID_REFRESH_TOKEN)
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
            ->with(1);

        $result = $this->service->revokeAllUserTokens(1);

        $this->assertSame(\dcardenasl\Ci4ApiCore\Support\OperationState::SUCCESS, $result->state);
    }
}
