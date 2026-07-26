<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\System;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Audit as AuditConfig;
use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;

/**
 * Health Check Controller
 *
 * ARCHITECTURAL DECISION: Does NOT extend ApiController
 *
 * Reason: Infrastructure endpoint requiring minimal overhead
 * - Called every 5-10 seconds by orchestrators (Kubernetes, Docker Swarm)
 * - No authentication or business logic needed
 * - Must be lightweight and fast to respond
 * - Industry standard pattern (Spring Boot Actuator, Express health checks)
 *
 * Direct instantiation of dependencies is intentional for performance.
 * This controller serves infrastructure/monitoring needs, not business API requests.
 *
 * @see CLAUDE.md "Architectural Exceptions"
 */
class HealthController extends Controller
{
    protected HealthChecker $healthChecker;
    protected AuditConfig $audit;

    public function __construct()
    {
        $this->healthChecker = $this->createHealthChecker();
        $this->audit = $this->createAuditConfig();
    }

    /**
     * Health check endpoint
     *
     * GET /health
     *
     * Returns health status of all system components
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        // Run all health checks
        $checks = $this->healthChecker->checkAll();

        // Determine overall status
        $overallStatus = $this->determineOverallStatus($checks);

        // Determine HTTP status code
        $statusCode = match ($overallStatus) {
            'healthy' => 200,
            'degraded' => 200, // Still operational
            'unhealthy' => 503,
            default => 500,
        };

        $response = [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $checks,
        ];

        return $this->response
            ->setJSON($response)
            ->setStatusCode($statusCode);
    }

    protected function createHealthChecker(): HealthChecker
    {
        return new HealthChecker();
    }

    protected function createAuditConfig(): AuditConfig
    {
        return config('Audit');
    }

    /**
     * Disk pressure should not take the Hub down when audit logging is enabled.
     * In that case, we downgrade an isolated critical disk check to degraded
     * so orchestrators do not restart the service while it is still operating.
     *
     * @param array<string, array<string, mixed>> $checks
     */
    protected function determineOverallStatus(array $checks): string
    {
        $overallStatus = $this->healthChecker->getOverallStatus($checks);

        if ($overallStatus !== 'unhealthy') {
            return $overallStatus;
        }

        if (! $this->audit->asyncEnabled) {
            return $overallStatus;
        }

        if (($checks['disk']['status'] ?? null) !== 'critical') {
            return $overallStatus;
        }

        return $this->hasOnlyDiskPressure($checks) ? 'degraded' : $overallStatus;
    }

    /**
     * @param array<string, array<string, mixed>> $checks
     */
    private function hasOnlyDiskPressure(array $checks): bool
    {
        foreach ($checks as $name => $check) {
            if ($name === 'disk') {
                continue;
            }

            if (isset($check['status']) && in_array($check['status'], ['unhealthy', 'critical'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Simple ping endpoint
     *
     * GET /ping
     *
     * Lightweight endpoint for basic availability check
     *
     * @return ResponseInterface
     */
    public function ping(): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
        ])->setStatusCode(200);
    }

    /**
     * Readiness check
     *
     * GET /ready
     *
     * Checks if application is ready to serve traffic
     *
     * @return ResponseInterface
     */
    public function ready(): ResponseInterface
    {
        // Check critical components only
        $databaseCheck = $this->healthChecker->checkDatabase();

        $isReady = ($databaseCheck['status'] ?? 'unhealthy') === 'healthy';

        $statusCode = $isReady ? 200 : 503;

        return $this->response->setJSON([
            'status' => $isReady ? 'ready' : 'not_ready',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $databaseCheck,
        ])->setStatusCode($statusCode);
    }

    /**
     * Liveness check
     *
     * GET /live
     *
     * Checks if application is alive (basic functionality)
     *
     * @return ResponseInterface
     */
    public function live(): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'alive',
            'timestamp' => date('Y-m-d H:i:s'),
            'uptime_seconds' => (int) (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
        ])->setStatusCode(200);
    }
}
