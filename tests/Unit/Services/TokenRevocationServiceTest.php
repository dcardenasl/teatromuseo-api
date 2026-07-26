<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Identity\RevokeAccessTokenRequestDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Services\Tokens\TokenRevocationService;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * TokenRevocationService Unit Tests
 */
class TokenRevocationServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected TokenRevocationService $service;
    protected TokenBlacklistModel $mockBlacklistModel;
    protected RefreshTokenModel $mockRefreshTokenModel;
    protected JwtServiceInterface $mockJwtService;
    protected AuditServiceInterface $mockAuditService;
    protected CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlacklistModel = $this->createMock(TokenBlacklistModel::class);
        $this->mockRefreshTokenModel = $this->createMock(RefreshTokenModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->mockCache = $this->createMock(CacheInterface::class);

        $this->service = new TokenRevocationService(
            $this->mockBlacklistModel,
            $this->mockRefreshTokenModel,
            $this->mockJwtService,
            $this->mockAuditService,
            $this->mockCache,
            new \App\Services\Tokens\BearerTokenService(),
            3600,
            60
        );
    }

    // ==================== REVOKE ACCESS TOKEN TESTS ====================

    public function testRevokeAccessTokenWithValidToken(): void
    {
        $jti = 'test-jti-123';
        $exp = time() + 3600;

        $this->mockJwtService
            ->method('decode')
            ->willReturn((object) ['jti' => $jti, 'exp' => $exp]);

        $this->mockBlacklistModel
            ->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $exp)
            ->willReturn(true);

        $this->mockCache
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $result = $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => 'Bearer valid-token-here',
        ], service('validation')));

        $this->assertTrue($result);
    }

    public function testRevokeAccessTokenWithoutHeaderThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([], service('validation')));
    }

    public function testRevokeAccessTokenWithEmptyHeaderThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => '',
        ], service('validation')));
    }

    public function testRevokeAccessTokenWithInvalidFormatThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => 'InvalidFormat token-here',
        ], service('validation')));
    }

    public function testRevokeAccessTokenWithInvalidTokenThrowsException(): void
    {
        $this->mockJwtService
            ->method('decode')
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => 'Bearer invalid-token',
        ], service('validation')));
    }

    public function testRevokeAccessTokenWithMissingJtiThrowsException(): void
    {
        $this->mockJwtService
            ->method('decode')
            ->willReturn((object) ['exp' => time() + 3600]); // Missing jti

        $this->expectException(AuthenticationException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => 'Bearer token-without-jti',
        ], service('validation')));
    }

    public function testRevokeAccessTokenWithMissingExpThrowsException(): void
    {
        $this->mockJwtService
            ->method('decode')
            ->willReturn((object) ['jti' => 'test-jti']); // Missing exp

        $this->expectException(AuthenticationException::class);

        $this->service->revokeAccessToken(new RevokeAccessTokenRequestDTO([
            'authorization_header' => 'Bearer token-without-exp',
        ], service('validation')));
    }

    // ==================== REVOKE TOKEN TESTS ====================

    public function testRevokeTokenWithValidJti(): void
    {
        $jti = 'test-jti-456';
        $exp = time() + 3600;

        $this->mockBlacklistModel
            ->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $exp)
            ->willReturn(true);

        $this->mockCache
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $result = $this->service->revokeToken($jti, $exp);

        $this->assertTrue($result);
    }

    public function testRevokeTokenFailureThrowsException(): void
    {
        $this->mockBlacklistModel
            ->method('addToBlacklist')
            ->willReturn(false);

        $this->expectException(BadRequestException::class);

        $this->service->revokeToken('test-jti', time() + 3600);
    }

    // ==================== IS REVOKED TESTS ====================

    public function testIsRevokedReturnsTrueForBlacklistedToken(): void
    {
        $jti = 'blacklisted-jti';

        $this->mockCache
            ->method('get')
            ->willReturn(null);

        $this->mockBlacklistModel
            ->expects($this->once())
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(true);

        $this->mockCache
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $result = $this->service->isRevoked($jti);

        $this->assertTrue($result);
    }

    public function testIsRevokedReturnsFalseForValidToken(): void
    {
        $jti = 'valid-jti';

        $this->mockCache
            ->method('get')
            ->willReturn(null);

        $this->mockBlacklistModel
            ->expects($this->once())
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(false);

        $this->mockCache
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $result = $this->service->isRevoked($jti);

        $this->assertFalse($result);
    }

    public function testIsRevokedUsesCacheWhenAvailable(): void
    {
        $jti = 'cached-jti';

        $this->mockCache
            ->method('get')
            ->willReturn(1); // Cached as revoked

        // Should NOT call database
        $this->mockBlacklistModel
            ->expects($this->never())
            ->method('isBlacklisted');

        $result = $this->service->isRevoked($jti);

        $this->assertTrue($result);
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensCallsModel(): void
    {
        $userId = 123;

        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($userId);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertTrue($result);
    }
}
