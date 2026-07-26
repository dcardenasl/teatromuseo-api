<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class MetricsControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAs('admin');
        putenv('METRICS_ENABLED=true');

        // Ensure static context is set for background model operations (Auditable trait)
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
    }

    protected function tearDown(): void
    {
        putenv('METRICS_ENABLED');
        parent::tearDown();
    }

    public function testMetricsRequiresAdmin(): void
    {
        $this->actAs('user');

        $result = $this->get('/api/v1/metrics');

        $result->assertStatus(403);
    }

    public function testMetricsEndpointsReturnSuccessForAdmin(): void
    {
        $endpoints = [
            '/api/v1/metrics',
            '/api/v1/metrics/requests',
            '/api/v1/metrics/slow-requests',
            '/api/v1/metrics/timeseries',
            '/api/v1/metrics/custom/example',
        ];

        foreach ($endpoints as $endpoint) {
            $result = $this->get($endpoint);

            $result->assertStatus(200);
        }
    }

    public function testTimeseriesReturnsParallelArraysForEachPeriod(): void
    {
        $expectedBuckets = [
            '1h' => 12,
            '24h' => 24,
            '7d' => 7,
            '30d' => 30,
        ];

        foreach ($expectedBuckets as $period => $bucketCount) {
            $result = $this->get('/api/v1/metrics/timeseries?period=' . $period);

            $result->assertStatus(200);
            $body = json_decode((string) $result->getJSON(), true);
            $data = $body['data'];

            $this->assertCount($bucketCount, $data['dates']);
            $this->assertCount($bucketCount, $data['requests']);
            $this->assertCount($bucketCount, $data['errors']);
            $this->assertCount($bucketCount, $data['latency']);
        }
    }

    public function testRecordMetricValidationAndSuccess(): void
    {
        $invalid = $this->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'value' => 1.5,
        ]);

        $invalid->assertStatus(422);

        $valid = $this->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'name' => 'example',
            'value' => 1.5,
            'tags' => ['env' => 'test'],
        ]);

        $valid->assertStatus(201);
    }

    public function testMetricsEndpointsReturn503WhenFeatureIsDisabled(): void
    {
        putenv('METRICS_ENABLED=false');

        $result = $this->get('/api/v1/metrics');
        $result->assertStatus(503);
    }
}
