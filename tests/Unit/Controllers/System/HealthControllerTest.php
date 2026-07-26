<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\System;

use App\Controllers\Api\V1\System\HealthController;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Audit as AuditConfig;
use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;

final class HealthControllerTest extends CIUnitTestCase
{
    public function testCriticalDiskPressureDegradesWhenAuditLoggingIsEnabled(): void
    {
        $checks = [
            'hub' => ['status' => 'healthy'],
            'disk' => ['status' => 'critical'],
            'writable' => ['status' => 'healthy'],
        ];

        $controller = $this->createController('unhealthy', true);

        $this->assertSame('degraded', $controller->exposeDetermineOverallStatus($checks));
    }

    public function testCriticalDiskPressureStaysUnhealthyWhenAuditLoggingIsDisabled(): void
    {
        $checks = [
            'hub' => ['status' => 'healthy'],
            'disk' => ['status' => 'critical'],
            'writable' => ['status' => 'healthy'],
        ];

        $controller = $this->createController('unhealthy', false);

        $this->assertSame('unhealthy', $controller->exposeDetermineOverallStatus($checks));
    }

    public function testOtherUnhealthyChecksStayUnhealthy(): void
    {
        $checks = [
            'hub' => ['status' => 'unhealthy'],
            'disk' => ['status' => 'critical'],
            'writable' => ['status' => 'healthy'],
        ];

        $controller = $this->createController('unhealthy', true);

        $this->assertSame('unhealthy', $controller->exposeDetermineOverallStatus($checks));
    }

    private function createController(string $overallStatus, bool $auditAsyncEnabled): HealthControllerTestProxy
    {
        $healthChecker = $this->createMock(HealthChecker::class);
        $healthChecker->method('getOverallStatus')->willReturn($overallStatus);

        $auditConfig = new class ($auditAsyncEnabled) extends AuditConfig {
            public function __construct(bool $asyncEnabled)
            {
                $this->asyncEnabled = $asyncEnabled;
            }
        };

        return new HealthControllerTestProxy($healthChecker, $auditConfig);
    }
}

final class HealthControllerTestProxy extends HealthController
{
    public function __construct(
        private readonly HealthChecker $healthCheckerMock,
        private readonly AuditConfig $auditConfig
    ) {
        parent::__construct();
    }

    protected function createHealthChecker(): HealthChecker
    {
        return $this->healthCheckerMock;
    }

    protected function createAuditConfig(): AuditConfig
    {
        return $this->auditConfig;
    }

    public function exposeDetermineOverallStatus(array $checks): string
    {
        return $this->determineOverallStatus($checks);
    }
}
