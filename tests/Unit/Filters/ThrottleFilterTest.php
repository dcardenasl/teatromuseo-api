<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Entities\ApiKeyEntity;
use App\Filters\ThrottleFilter;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

/**
 * ThrottleFilter Unit Tests
 *
 * Tests general rate limiting for API endpoints, including the API key
 * stratified rate limiting strategy.
 */
class ThrottleFilterTest extends CIUnitTestCase
{
    protected ThrottleFilter $filter;
    protected CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter    = new ThrottleFilter();
        $this->mockCache = $this->createMock(CacheInterface::class);

        // Inject mocked cache into Services
        Services::injectMock('cache', $this->mockCache);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset(true);
    }

    /**
     * Helper: Create mock ApiRequest with IP address, optional userId, and optional X-App-Key header.
     */
    private function createMockRequest(
        string $ip = '127.0.0.1',
        ?int $userId = null,
        ?string $appKey = null
    ): ApiRequest {
        $request = $this->createMock(ApiRequest::class);

        $request->method('getIPAddress')
            ->willReturn($ip);

        $request->method('getAuthUserId')
            ->willReturn($userId);

        // getHeaderLine returns the X-App-Key header or empty string
        $request->method('getHeaderLine')
            ->willReturnCallback(function (string $header) use ($appKey) {
                if (strtolower($header) === 'x-app-key') {
                    return $appKey ?? '';
                }
                // No Authorization header by default
                return '';
            });

        return $request;
    }

    // ==================== EXISTING TESTS (no API key path) ====================

    public function testBeforeAllowsRequestsWithinLimit(): void
    {
        $request   = $this->createMockRequest('192.168.1.1');
        $apiConfig = config('Api');
        $limit     = $apiConfig->rateLimitRequests;
        $window    = $apiConfig->rateLimitWindow;

        // Simulate first request (cache returns null)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->stringContains('rate_limit_'),
                1,
                $window
            )
            ->willReturn(true);

        // Expect setRateLimitInfo to be called with correct limit and remaining
        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) use ($limit) {
                return isset($info['limit'], $info['remaining'], $info['reset'])
                    && $info['limit'] === $limit
                    && $info['remaining'] === $limit - 1;
            }));

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testBeforeBlocksRequestsExceedingLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1');
        $limit   = config('Api')->rateLimitRequests;

        // Simulate exceeded limit (counter at or above limit)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn($limit);

        $this->mockCache->expects($this->never())
            ->method('save');

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->getStatusCode());
    }

    public function testBeforeReturns429WhenThrottled(): void
    {
        $request = $this->createMockRequest('192.168.1.1');
        $limit   = config('Api')->rateLimitRequests;

        $this->mockCache->method('get')
            ->willReturn($limit); // Limit reached

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
        $request = $this->createMockRequest('10.0.0.5');

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('rate_limit_ip_'))
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo');

        $this->filter->before($request);

        // Test passes if cache key contains IP-based identifier
        $this->assertTrue(true);
    }

    public function testBeforeEnforcesBothIpAndUserLimitsForAuthenticatedRequests(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 123);

        // Expects two get() calls: one for IP key, one for user key
        $this->mockCache->expects($this->exactly(2))
            ->method('get')
            ->willReturn(null);

        // Expects two save() calls: one for IP key, one for user key
        $this->mockCache->expects($this->exactly(2))
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo');

        $this->filter->before($request);

        // Authenticated requests apply both IP and user-based limits independently
        $this->assertTrue(true);
    }

    public function testBeforeIncrementsRequestCount(): void
    {
        $request = $this->createMockRequest('192.168.1.1');
        $limit   = config('Api')->rateLimitRequests;

        // Simulate 5th request (counter already exists in cache at 4)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(4);

        // save() must NOT be called — that would reset the window TTL (fixed-window bug)
        $this->mockCache->expects($this->never())->method('save');

        // increment() preserves the original TTL
        $this->mockCache->expects($this->once())
            ->method('increment')
            ->with($this->stringContains('rate_limit_ip_'))
            ->willReturn(5);

        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) use ($limit) {
                return $info['remaining'] === $limit - 5; // limit - (counter+1)
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testSubsequentRequestsUseIncrementNotSave(): void
    {
        $request = $this->createMockRequest('192.168.1.1');
        $limit   = config('Api')->rateLimitRequests;

        // Simulate a mid-window request (counter already exists at 10)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(10);

        // save() must NEVER be called on subsequent requests — it would reset the window TTL
        $this->mockCache->expects($this->never())->method('save');

        // increment() is called to advance the counter without touching the TTL
        $this->mockCache->expects($this->once())
            ->method('increment')
            ->willReturn(11);

        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(fn ($info) => $info['remaining'] === $limit - 11)); // limit - (counter+1)

        $result = $this->filter->before($request);
        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testAfterSetsRateLimitHeaders(): void
    {
        $request  = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $rateLimitInfo = [
            'limit'     => 60,
            'remaining' => 45,
            'reset'     => time() + 60,
        ];

        $request->method('getRateLimitInfo')
            ->willReturn($rateLimitInfo);

        $result = $this->filter->after($request, $response);

        $this->assertTrue($result->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Reset'));
        $this->assertEquals('60', $result->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('45', $result->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testAfterDoesNotSetHeadersWhenNoRateLimitInfo(): void
    {
        $request  = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $request->method('getRateLimitInfo')
            ->willReturn(null);

        $result = $this->filter->after($request, $response);

        $this->assertFalse($result->hasHeader('X-RateLimit-Limit'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Reset'));
    }

    public function testBeforeRespectsCustomEnvironmentLimits(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                1,
                $this->greaterThan(0)
            );

        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['limit'] > 0 && $info['remaining'] >= 0;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    // ==================== API KEY PATH TESTS ====================

    public function testInvalidApiKeyReturns401(): void
    {
        // Provide a non-empty X-App-Key header; cache returns null (miss) and model returns null
        $request = $this->createMockRequest('192.168.1.1', null, 'apk_invalidkeyvalue');

        // Cache miss for the hash lookup
        $this->mockCache->method('get')->willReturn(null);
        // No save expected (we don't cache misses)
        $this->mockCache->expects($this->never())->method('save');

        // Inject a mock ApiKeyModel that returns null (key not found)
        $mockApiKeyModel = new class () extends \App\Models\ApiKeyModel {
            public function __construct()
            {
                // Skip DB constructor
            }

            public function findByHash(string $hash): ?\App\Entities\ApiKeyEntity
            {
                return null;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function first()
            {
                return null;
            }
        };
        Services::injectMock('apiKeyModel', $mockApiKeyModel);

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(401, $result->getStatusCode());

        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertEquals(401, $body['code']);
        $this->assertArrayHasKey('api_key', $body['errors']);
    }

    public function testMissingApiKeyFallsBackToIpRateLimit(): void
    {
        // No X-App-Key header → should use IP rate limiting
        $request = $this->createMockRequest('10.10.10.10', null, null);

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('rate_limit_ip_'))
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo');

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testCachedApiKeyIsUsedWithoutDbLookup(): void
    {
        // Pre-populate cache with a valid key array so model is never hit
        $entity = new ApiKeyEntity();
        $entity->id                   = 42;
        $entity->is_active            = true;
        $entity->rate_limit_requests  = 600;
        $entity->rate_limit_window    = 60;
        $entity->user_rate_limit      = 60;
        $entity->ip_rate_limit        = 200;

        $rawKey = 'apk_cachedkeyvalue123456789012345678901234567890';
        $hash   = hash('sha256', $rawKey);
        $cacheKey = 'api_key_' . $hash;

        // First get() call returns the cached entity array
        // Subsequent get() calls (rate limit counters) return null (first request)
        $callCount = 0;
        $this->mockCache->method('get')
            ->willReturnCallback(function (string $key) use ($cacheKey, $entity, &$callCount) {
                if ($key === $cacheKey) {
                    $callCount++;
                    return $entity->toArray();
                }
                return null;
            });

        $this->mockCache->method('save')->willReturn(true);

        $request = $this->createMockRequest('192.168.0.1', null, $rawKey);
        $request->expects($this->once())->method('setRateLimitInfo');
        $request->expects($this->once())->method('setAppKeyId')->with(42);

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
        // Cache was consulted for the api_key lookup
        $this->assertGreaterThan(0, $callCount);
    }
}
