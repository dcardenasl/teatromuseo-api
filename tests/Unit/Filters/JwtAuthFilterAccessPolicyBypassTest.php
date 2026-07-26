<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\JwtAuthFilter;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Api as ApiConfig;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use ReflectionClass;

/**
 * Verifies that the access-policy bypass list lives in Config\Api
 * (not hardcoded in JwtAuthFilter) and that the filter consults it
 * dynamically. Adding or removing a route in config must change behavior
 * without editing the filter source.
 */
class JwtAuthFilterAccessPolicyBypassTest extends CIUnitTestCase
{
    private JwtAuthFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new JwtAuthFilter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset(true);
    }

    private function invokeBypassCheck(string $path): bool
    {
        $request = $this->createMock(ApiRequest::class);
        $uri     = new URI('http://localhost' . $path);
        $request->method('getUri')->willReturn($uri);

        $reflection = new ReflectionClass($this->filter);
        $method     = $reflection->getMethod('shouldBypassAccessPolicy');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->filter, $request);
    }

    public function testDefaultConfigIncludesResendVerification(): void
    {
        $api = new ApiConfig();

        $this->assertContains('api/v1/auth/resend-verification', $api->accessPolicyBypassRoutes);
    }

    public function testFilterBypassesRouteListedInConfig(): void
    {
        $this->assertTrue($this->invokeBypassCheck('/api/v1/auth/resend-verification'));
    }

    public function testFilterDoesNotBypassUnlistedRoute(): void
    {
        $this->assertFalse($this->invokeBypassCheck('/api/v1/users'));
        $this->assertFalse($this->invokeBypassCheck('/api/v1/auth/me'));
    }

    public function testFilterReadsBypassListDynamicallyFromConfig(): void
    {
        // Override the config — the filter must pick up the change without code edits.
        $api                              = config('Api');
        $api->accessPolicyBypassRoutes    = ['api/v1/custom/exemption'];

        $this->assertTrue($this->invokeBypassCheck('/api/v1/custom/exemption'));
        $this->assertFalse($this->invokeBypassCheck('/api/v1/auth/resend-verification'));
    }

    public function testFilterToleratesLeadingSlashInConfigEntries(): void
    {
        $api                              = config('Api');
        $api->accessPolicyBypassRoutes    = ['/api/v1/with/leading/slash'];

        $this->assertTrue($this->invokeBypassCheck('/api/v1/with/leading/slash'));
    }
}
