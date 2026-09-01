<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\TokenRevocationServiceInterface;
use App\Models\UserModel;
use App\Services\Auth\TokenIntrospectionService;
use App\Services\Iam\EffectivePermissionsResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit test suite for TokenIntrospectionService (auth introspection boundary).
 */
class TokenIntrospectionServiceTest extends CIUnitTestCase
{
    private JwtServiceInterface $jwtService;
    private TokenRevocationServiceInterface $tokenRevocationService;
    private EffectivePermissionsResolver $effectivePermissionsResolver;
    private UserModel $userModel;
    private TokenIntrospectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = $this->createMock(JwtServiceInterface::class);
        $this->tokenRevocationService = $this->createMock(TokenRevocationServiceInterface::class);
        $this->effectivePermissionsResolver = $this->createMock(EffectivePermissionsResolver::class);
        $this->userModel = $this->createMock(UserModel::class);
        $this->userModel->method('find')->willReturn((object) ['auth_token_version' => 0]);

        $this->service = new TokenIntrospectionService(
            $this->jwtService,
            $this->tokenRevocationService,
            $this->effectivePermissionsResolver,
            $this->userModel
        );
    }

    private function makeDto(string $token): IntrospectRequestDTO
    {
        /** @var IntrospectRequestDTO */
        return \Config\Services::requestDtoFactory()->make(IntrospectRequestDTO::class, ['token' => $token]);
    }

    public function testIntrospectReturnsInvalidWhenJwtDecodeFails(): void
    {
        $this->jwtService
            ->method('decode')
            ->with('invalid-token')
            ->willReturn(null);

        $dto = $this->makeDto('invalid-token');
        $result = $this->service->introspect($dto);

        $this->assertFalse($result->valid);
        $this->assertNull($result->uid);
        $this->assertSame('invalid_or_expired', $result->error);
        $this->assertEmpty($result->permissions);
    }

    public function testIntrospectReturnsInvalidWhenTokenIsRevoked(): void
    {
        $decoded = (object) [
            'jti' => 'revoked-jti-123',
            'uid' => 10,
            'exp' => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('revoked-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('revoked-jti-123')
            ->willReturn(true);

        $dto = $this->makeDto('revoked-token');
        $result = $this->service->introspect($dto);

        $this->assertFalse($result->valid);
        $this->assertNull($result->uid);
        $this->assertSame('revoked', $result->error);
        $this->assertEmpty($result->permissions);
    }

    public function testIntrospectResolvesUserPermissionsForApplication(): void
    {
        $decoded = (object) [
            'jti' => 'jti-valid',
            'uid' => 42,
            'exp' => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('valid-user-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('jti-valid')
            ->willReturn(false);

        $this->effectivePermissionsResolver
            ->method('resolve')
            ->with(42, 5)
            ->willReturn(['catalog.read', 'catalog.write']);

        $dto = $this->makeDto('valid-user-token');
        $result = $this->service->introspect($dto, 5);

        $this->assertTrue($result->valid);
        $this->assertSame(42, $result->uid);
        $this->assertSame(5, $result->app_id);
        $this->assertNull($result->error);
        $this->assertSame(['catalog.read', 'catalog.write'], $result->permissions);
    }

    public function testIntrospectFallsBackToJwtScopeWhenNoApplicationIdProvided(): void
    {
        $decoded = (object) [
            'jti' => 'service-jti',
            'scope' => ['cms.read', 'cms.write'],
            'exp' => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('service-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('service-jti')
            ->willReturn(false);

        $dto = $this->makeDto('service-token');
        $result = $this->service->introspect($dto, null);

        $this->assertTrue($result->valid);
        $this->assertNull($result->uid);
        $this->assertNull($result->app_id);
        $this->assertSame(['cms.read', 'cms.write'], $result->permissions);
    }

    public function testIntrospectReturnsEmptyPermissionsWhenNoScopeAndNoAppId(): void
    {
        $decoded = (object) [
            'jti' => 'minimal-jti',
            'exp' => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('minimal-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('minimal-jti')
            ->willReturn(false);

        $dto = $this->makeDto('minimal-token');
        $result = $this->service->introspect($dto);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->permissions);
    }

    public function testIntrospectSkipsRevocationCheckWhenTokenHasNoJti(): void
    {
        $decoded = (object) [
            'uid' => 7,
            'exp' => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('no-jti-token')
            ->willReturn($decoded);

        $this->tokenRevocationService->expects($this->never())->method('isRevoked');
        $this->effectivePermissionsResolver->expects($this->never())->method('resolve');

        $dto = $this->makeDto('no-jti-token');
        $result = $this->service->introspect($dto);

        $this->assertTrue($result->valid);
        $this->assertSame(7, $result->uid);
        $this->assertNull($result->error);
    }

    public function testIntrospectFallsBackToScopeWhenUidPresentButNoApplicationId(): void
    {
        // A user-bound token introspected by a caller that didn't pass an
        // applicationId (e.g. the Hub's own JwtAuthFilter) must not attempt
        // to re-resolve effective permissions — it has no app to scope to.
        $decoded = (object) [
            'jti'   => 'user-no-app-jti',
            'uid'   => 99,
            'scope' => ['users.read'],
            'exp'   => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('user-no-app-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('user-no-app-jti')
            ->willReturn(false);

        $this->effectivePermissionsResolver->expects($this->never())->method('resolve');

        $dto = $this->makeDto('user-no-app-token');
        $result = $this->service->introspect($dto, null);

        $this->assertTrue($result->valid);
        $this->assertSame(99, $result->uid);
        $this->assertNull($result->app_id);
        $this->assertSame(['users.read'], $result->permissions);
    }

    public function testIntrospectTreatsZeroUidAsAbsentAndFallsBackToScope(): void
    {
        // uid=0 is falsy/invalid as a subject id (service tokens carry no
        // uid at all, never uid=0) — must not be treated as a real user id.
        $decoded = (object) [
            'jti'   => 'zero-uid-jti',
            'uid'   => 0,
            'scope' => ['cms.read'],
            'exp'   => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('zero-uid-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('zero-uid-jti')
            ->willReturn(false);

        $this->effectivePermissionsResolver->expects($this->never())->method('resolve');

        $dto = $this->makeDto('zero-uid-token');
        $result = $this->service->introspect($dto, 5);

        $this->assertTrue($result->valid);
        $this->assertSame(0, $result->uid);
        $this->assertSame(['cms.read'], $result->permissions);
    }

    public function testIntrospectReturnsEmptyPermissionsWhenScopeClaimIsNotAnArray(): void
    {
        // Defensive handling of a malformed/unexpected claim shape — a
        // scalar `scope` must not crash resolvePermissions().
        $decoded = (object) [
            'jti'   => 'bad-scope-jti',
            'scope' => 'not-an-array',
            'exp'   => 1800000000,
        ];

        $this->jwtService
            ->method('decode')
            ->with('bad-scope-token')
            ->willReturn($decoded);

        $this->tokenRevocationService
            ->method('isRevoked')
            ->with('bad-scope-jti')
            ->willReturn(false);

        $dto = $this->makeDto('bad-scope-token');
        $result = $this->service->introspect($dto);

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->permissions);
    }

    public function testIntrospectPreservesExpiryClaim(): void
    {
        $decoded = (object) [
            'jti' => 'exp-jti',
            'uid' => 3,
            'exp' => 1735689600,
        ];

        $this->jwtService->method('decode')->with('exp-token-123')->willReturn($decoded);
        $this->tokenRevocationService->method('isRevoked')->with('exp-jti')->willReturn(false);

        $dto = $this->makeDto('exp-token-123');
        $result = $this->service->introspect($dto);

        $this->assertSame(1735689600, $result->exp);
    }

    public function testIntrospectReturnsNullExpWhenClaimMissing(): void
    {
        $decoded = (object) [
            'jti' => 'no-exp-jti',
            'uid' => 4,
        ];

        $this->jwtService->method('decode')->with('no-exp-token')->willReturn($decoded);
        $this->tokenRevocationService->method('isRevoked')->with('no-exp-jti')->willReturn(false);

        $dto = $this->makeDto('no-exp-token');
        $result = $this->service->introspect($dto);

        $this->assertTrue($result->valid);
        $this->assertNull($result->exp);
    }
}
