<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;
use Tests\Support\IntegrationTestCase;

/**
 * HealthChecker Tests
 */
class HealthCheckerTest extends IntegrationTestCase
{
    protected HealthChecker $healthChecker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthChecker = new HealthChecker();
    }

    public function testCheckDatabaseReturnsHealthyWhenConnected(): void
    {
        $result = $this->healthChecker->checkDatabase();

        $this->assertEquals('healthy', $result['status']);
        $this->assertArrayHasKey('response_time_ms', $result);
        $this->assertGreaterThan(0, $result['response_time_ms']);
    }

    public function testCheckDiskSpaceReturnsStatus(): void
    {
        $result = $this->healthChecker->checkDiskSpace();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('free_space_mb', $result);
        $this->assertArrayHasKey('total_space_mb', $result);
        $this->assertArrayHasKey('used_percentage', $result);
        $this->assertGreaterThan(0, $result['free_space_mb']);
    }

    public function testCheckWritableFoldersReturnsHealthyWhenAllWritable(): void
    {
        $result = $this->healthChecker->checkWritableFolders();

        // In test environment, folders should be writable
        $this->assertEquals('healthy', $result['status']);
        $this->assertStringContainsString('accessible', $result['message']);
    }

    public function testCheckEmailReturnsUnconfiguredInTestEnvironment(): void
    {
        $result = $this->healthChecker->checkEmail();

        // In test environment, email might not be configured
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCheckRedisReturnsUnavailableWhenExtensionNotLoaded(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is loaded');
        }

        $result = $this->healthChecker->checkRedis();

        $this->assertEquals('unavailable', $result['status']);
    }

    public function testCheckQueueReturnsHealthyWhenJobsTableExists(): void
    {
        $result = $this->healthChecker->checkQueue();

        $this->assertEquals('healthy', $result['status']);
        $this->assertArrayHasKey('pending_jobs', $result);
        $this->assertArrayHasKey('processing_jobs', $result);
        $this->assertArrayHasKey('failed_jobs', $result);
    }

    public function testCheckAllReturnsAllChecks(): void
    {
        $result = $this->healthChecker->checkAll();

        $this->assertArrayHasKey('database', $result);
        $this->assertArrayHasKey('disk', $result);
        $this->assertArrayHasKey('writable', $result);
    }

    public function testGetOverallStatusReturnsHealthyWhenAllHealthy(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'disk' => ['status' => 'healthy'],
        ];

        $status = $this->healthChecker->getOverallStatus($checks);

        $this->assertEquals('healthy', $status);
    }

    public function testGetOverallStatusReturnsUnhealthyWhenAnyUnhealthy(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'disk' => ['status' => 'unhealthy'],
        ];

        $status = $this->healthChecker->getOverallStatus($checks);

        $this->assertEquals('unhealthy', $status);
    }

    public function testGetOverallStatusReturnsDegradedWhenWarning(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'disk' => ['status' => 'warning'],
        ];

        $status = $this->healthChecker->getOverallStatus($checks);

        $this->assertEquals('degraded', $status);
    }

    public function testGetOverallStatusPrioritizesUnhealthyOverWarning(): void
    {
        $checks = [
            'database' => ['status' => 'unhealthy'],
            'disk' => ['status' => 'warning'],
        ];

        $status = $this->healthChecker->getOverallStatus($checks);

        $this->assertEquals('unhealthy', $status);
    }
}
