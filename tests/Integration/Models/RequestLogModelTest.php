<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RequestLogModel;
use Tests\Support\IntegrationTestCase;

class RequestLogModelTest extends IntegrationTestCase
{
    public function testGetStatsReturnsSloAndBreakdownMetrics(): void
    {
        $model = new RequestLogModel();
        $model->builder()->truncate();
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['response_code' => 200, 'response_time' => 100],
            ['response_code' => 201, 'response_time' => 200],
            ['response_code' => 302, 'response_time' => 300],
            ['response_code' => 404, 'response_time' => 400],
            ['response_code' => 500, 'response_time' => 500],
        ];

        foreach ($rows as $row) {
            $model->insert([
                'method' => 'GET',
                'uri' => '/api/v1/test',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'response_code' => $row['response_code'],
                'response_time' => $row['response_time'],
                'created_at' => $now,
            ]);
        }

        $stats = $model->getStats('day');

        $this->assertSame(5, $stats['total_requests']);
        $this->assertSame(3, $stats['successful_requests']);
        $this->assertSame(2, $stats['failed_requests']);
        $this->assertSame(300.0, (float) $stats['avg_response_time_ms']);
        $this->assertSame(500.0, (float) $stats['p95_response_time_ms']);
        $this->assertSame(500.0, (float) $stats['p99_response_time_ms']);
        $this->assertSame(40.0, (float) $stats['error_rate_percent']);
        $this->assertSame(60.0, (float) $stats['availability_percent']);
        $this->assertSame(2, $stats['status_code_breakdown']['2xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['3xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['4xx']);
        $this->assertSame(1, $stats['status_code_breakdown']['5xx']);
        $this->assertArrayHasKey('slo', $stats);
        $this->assertArrayHasKey('p95_target_ms', $stats['slo']);
        $this->assertArrayHasKey('p95_target_met', $stats['slo']);
    }

    public function testGetTimeseriesBucketsRequestsAndFillsGapsWithZeros(): void
    {
        $model = new RequestLogModel();
        $model->builder()->truncate();

        $model->insert([
            'method' => 'GET',
            'uri' => '/api/v1/test',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'response_code' => 200,
            'response_time' => 120,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $series = $model->getTimeseries('1h');

        $this->assertCount(12, $series['dates']);
        $this->assertCount(12, $series['requests']);
        $this->assertCount(12, $series['errors']);
        $this->assertCount(12, $series['latency']);
        $this->assertSame(1, array_sum($series['requests']));
        $this->assertSame(0, array_sum($series['errors']));
    }

    public function testSlowRequestsCanBeBoundToTheSameWindowAsTheDashboardStats(): void
    {
        $model = new RequestLogModel();
        $model->builder()->truncate();

        $model->insert([
            'method' => 'GET',
            'uri' => '/api/v1/old-slow-request',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'response_code' => 200,
            'response_time' => 5000,
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        $model->insert([
            'method' => 'GET',
            'uri' => '/api/v1/recent-slow-request',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'response_code' => 200,
            'response_time' => 1500,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $slow = $model->getSlowRequests(1000, 5, '24h');

        $this->assertCount(1, $slow);
        $this->assertSame('/api/v1/recent-slow-request', $slow[0]['uri']);
    }
}
