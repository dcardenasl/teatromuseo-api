<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Entities\ApiKeyEntity;
use App\Filters\AuthThrottleFilter;
use App\Models\ApiKeyModel;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Api as ApiConfig;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

/**
 * AuthThrottleFilter Unit Tests
 *
 * Tests stricter rate limiting for authentication endpoints.
 * Critical for preventing brute-force attacks and credential stuffing.
 *
 * The general auth rate limit (`Config\Api::$authRateLimitRequests` /
 * `$authRateLimitWindow`) is environment-dependent (relaxed under
 * `ENVIRONMENT === 'development'` to keep local workflows usable). These
 * tests inject a fixed `Config\Api` fixture via `Factories::injectMock()` —
 * the same pattern used by `DeprecationHeadersFilterTest` — so assertions
 * stay deterministic regardless of the ambient `.env` / `CI_ENVIRONMENT`.
 */
class AuthThrottleFilterTest extends CIUnitTestCase
{
    private const DEFAULT_MAX_ATTEMPTS = 3;
    private const DEFAULT_WINDOW = 3600;

    protected AuthThrottleFilter $filter;
    protected CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new AuthThrottleFilter();
        $this->mockCache = $this->createMock(CacheInterface::class);

        Services::injectMock('cache', $this->mockCache);

        $apiConfig = new ApiConfig();
        $apiConfig->authRateLimitRequests = self::DEFAULT_MAX_ATTEMPTS;
        $apiConfig->authRateLimitWindow = self::DEFAULT_WINDOW;
        Factories::injectMock('config', 'Api', $apiConfig);
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        Services::reset(true);
        parent::tearDown();
    }

    /**
     * Helper: Create mock ApiRequest with IP address
     */
    private function createMockRequest(
        string $ip = '127.0.0.1',
        string $path = 'auth/login',
        ?string $appKey = null,
        ?string $authorization = null
    ): ApiRequest {
        $request = $this->createMock(ApiRequest::class);
        $uri = $this->createMock(\CodeIgniter\HTTP\URI::class);

        $request->method('getIPAddress')
            ->willReturn($ip);

        $request->method('getUri')
            ->willReturn($uri);

        $uri->method('getPath')
            ->willReturn($path);

        $request->method('getHeaderLine')
            ->willReturnCallback(function (string $header) use ($appKey, $authorization): string {
                if (strtolower($header) === 'x-app-key') {
                    return $appKey ?? '';
                }

                if (strtolower($header) === 'authorization') {
                    return $authorization ?? '';
                }

                return '';
            });

        $request->method('getAuthUserId')
            ->willReturn(null);

        return $request;
    }

    // ==================== TEST CASES ====================

    public function testBeforeAllowsRequestsWithinLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        // Simulate first auth attempt (cache returns null)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->stringContains('auth_rate_limit_'),
                1,
                $this->greaterThan(0)
            )
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function ($info) {
                return isset($info['limit'], $info['remaining'], $info['reset'])
                    && $info['remaining'] >= 0;
            }));

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testBeforeBlocksRequestsExceedingLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        // Simulate the counter already at the configured max attempts.
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(self::DEFAULT_MAX_ATTEMPTS);

        $this->mockCache->expects($this->never())
            ->method('save');

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->getStatusCode());
    }

    public function testBeforeReturns429WhenThrottled(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        $this->mockCache->method('get')
            ->willReturn(self::DEFAULT_MAX_ATTEMPTS); // Limit reached

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->getStatusCode());
        $this->assertTrue($result->hasHeader('Retry-After'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Limit'));
        $this->assertEquals('0', $result->getHeaderLine('X-RateLimit-Remaining'));

        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertEquals(429, $body['code']);
        $this->assertArrayHasKey('retry_after', $body);
    }

    public function testBeforeUsesIPAddressAsIdentifier(): void
    {
        $request = $this->createMockRequest('10.0.0.5', 'auth/login');

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('auth_rate_limit_'))
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo');

        $this->filter->before($request);

        // Auth rate limit uses IP-only (no user context before auth)
        $this->assertTrue(true);
    }

    public function testBeforeIncrementsAttemptCount(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        // Simulate 3rd attempt (counter already exists in cache)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(2);

        // save() must NOT be called — that would reset the window TTL (fixed-window bug)
        $this->mockCache->expects($this->never())->method('save');

        // increment() preserves the original TTL
        $this->mockCache->expects($this->once())
            ->method('increment')
            ->with($this->stringContains('auth_rate_limit_'))
            ->willReturn(3);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo');

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testBeforeAppliesGeneralLimitToLoginRoute(): void
    {
        // auth/login has no route-specific override: it shares the general
        // authRateLimitRequests config (unlike auth/refresh, see
        // testBeforeAppliesRelaxedLimitForRefreshRoute below). A dedicated
        // route override for login was removed because it could only ever
        // loosen production (the general limit is already the strictest
        // value); brute-force protection now comes purely from the shared
        // config, tightened per-environment in Config\Api.
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        $this->mockCache->method('get')->willReturn(null);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['limit'] === self::DEFAULT_MAX_ATTEMPTS;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testBeforeKeepsNonLoginAuthRoutesOnDefaultLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/register');

        $this->mockCache->method('get')->willReturn(null);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['limit'] === self::DEFAULT_MAX_ATTEMPTS;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testBeforeAppliesRelaxedLimitForRefreshRoute(): void
    {
        // auth/refresh requires an already-valid refresh token (not a
        // guessable credential), so it gets a relaxed override instead of
        // sharing the brute-force-strength limit applied to login/register.
        $request = $this->createMockRequest('192.168.1.1', 'auth/refresh');

        $this->mockCache->method('get')->willReturn(null);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['limit'] === 30 && $info['remaining'] === 29;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testAfterSetsRateLimitHeaders(): void
    {
        $request = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $rateLimitInfo = [
            'limit' => 5,
            'remaining' => 3,
            'reset' => time() + 900,
        ];

        $request->method('getAuthRateLimitInfo')
            ->willReturn($rateLimitInfo);

        $result = $this->filter->after($request, $response);

        $this->assertTrue($result->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Reset'));
        $this->assertEquals('5', $result->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('3', $result->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testAfterDoesNotSetHeadersWhenNoRateLimitInfo(): void
    {
        $request = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $request->method('getAuthRateLimitInfo')
            ->willReturn(null);

        $result = $this->filter->after($request, $response);

        $this->assertFalse($result->hasHeader('X-RateLimit-Limit'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Reset'));
    }

    public function testBeforeRespectsCustomEnvironmentLimits(): void
    {
        // Confirms the filter reads whatever authRateLimitRequests /
        // authRateLimitWindow the active Config\Api instance carries — i.e.
        // it doesn't hardcode a limit of its own — by overriding the
        // fixture injected in setUp() with different values.
        $apiConfig = new ApiConfig();
        $apiConfig->authRateLimitRequests = 7;
        $apiConfig->authRateLimitWindow = 120;
        Factories::injectMock('config', 'Api', $apiConfig);

        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->expects($this->once())
            ->method('save')
            ->with($this->anything(), 1, 120);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['limit'] === 7 && $info['remaining'] === 6;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testBeforeUsesConfiguredWindowForAuthAttempts(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login');

        $this->mockCache->method('get')->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with($this->anything(), 1, self::DEFAULT_WINDOW);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo');

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testBeforeWithInvalidApiKeyReturnsUnauthorized(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login', 'invalid-key');
        $apiKeyModel = $this->createMock(ApiKeyModel::class);

        Services::injectMock('apiKeyModel', $apiKeyModel);

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith('api_key_'))
            ->willReturn(null);

        $apiKeyModel->expects($this->once())
            ->method('findByHash')
            ->willReturn(null);

        $this->mockCache->expects($this->never())
            ->method('save');

        $request->expects($this->never())
            ->method('setAuthRateLimitInfo');

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(401, $result->getStatusCode());
        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertArrayHasKey('api_key', $body['errors']);
    }

    public function testBeforeWithValidApiKeyUsesApiKeyLimits(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 'auth/login', 'valid-key');
        $apiKeyModel = $this->createMock(ApiKeyModel::class);
        $apiKey = new ApiKeyEntity([
            'id' => 10,
            'name' => 'Auth Client',
            'key_prefix' => 'auth',
            'key_hash' => hash('sha256', 'valid-key'),
            'is_active' => 1,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60,
            'user_rate_limit' => 50,
            'ip_rate_limit' => 20,
        ]);

        Services::injectMock('apiKeyModel', $apiKeyModel);

        $this->mockCache->method('get')
            ->willReturnCallback(static fn (string $key): ?int => str_starts_with($key, 'api_key_10') ? null : null);

        $this->mockCache->expects($this->exactly(3))
            ->method('save')
            ->willReturn(true);

        $apiKeyModel->expects($this->once())
            ->method('findByHash')
            ->willReturn($apiKey);

        $request->expects($this->once())
            ->method('setAuthRateLimitInfo')
            ->with($this->callback(function (array $info): bool {
                return $info['limit'] === 100 && $info['remaining'] === 99;
            }));

        $request->expects($this->once())
            ->method('setAppKeyId')
            ->with(10);

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testBeforeUsesDifferentBucketsPerAuthRoute(): void
    {
        $loginRequest = $this->createMockRequest('192.168.1.1', 'auth/login');
        $registerRequest = $this->createMockRequest('192.168.1.1', 'auth/register');

        $keys = [];
        $this->mockCache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $key) use (&$keys): ?int {
                $keys[] = $key;
                return null;
            });

        $this->mockCache->expects($this->exactly(2))
            ->method('save')
            ->willReturn(true);

        $loginRequest->expects($this->once())->method('setAuthRateLimitInfo');
        $registerRequest->expects($this->once())->method('setAuthRateLimitInfo');

        $this->filter->before($loginRequest);
        $this->filter->before($registerRequest);

        $this->assertCount(2, array_unique($keys));
        $this->assertNotSame($keys[0], $keys[1]);
    }
}
