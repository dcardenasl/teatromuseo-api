<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\MetricModel;
use Tests\Support\IntegrationTestCase;

/**
 * MetricModel Integration Tests
 */
class MetricModelTest extends IntegrationTestCase
{
    protected MetricModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new MetricModel();
    }

    public function testRecordInsertsMetricAndReturnsId(): void
    {
        $id = $this->model->record('response_time', 12.5, ['route' => '/api/v1/users']);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->model->find($id);
        $this->assertSame('response_time', $row['metric_name']);
        $this->assertNotNull($row['tags']);
    }

    public function testRecordWithoutTagsStoresNullTags(): void
    {
        $id = $this->model->record('cache_hit', 1.0);

        $row = $this->model->find($id);
        $this->assertNull($row['tags']);
    }

    public function testGetByNameReturnsOnlyMatchingRecentMetrics(): void
    {
        $this->model->record('metric_a', 1.0);
        $this->model->record('metric_b', 2.0);

        $rows = $this->model->getByName('metric_a', 'day');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('metric_a', is_object($row) ? $row->metric_name : $row['metric_name']);
        }
    }

    public function testGetByNameExcludesMetricsOutsideThePeriod(): void
    {
        $name = 'old_metric_' . uniqid('', true);
        $this->model->insert([
            'metric_name' => $name,
            'metric_value' => 3.0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        $rows = $this->model->getByName($name, 'hour');

        $this->assertSame([], $rows);
    }

    public function testGetAggregatedComputesStatistics(): void
    {
        $name = 'latency_' . uniqid('', true);
        $this->model->record($name, 10.0);
        $this->model->record($name, 20.0);
        $this->model->record($name, 30.0);

        $result = $this->model->getAggregated($name, 'day');

        $this->assertSame($name, $result['metric_name']);
        $this->assertSame('day', $result['period']);
        $this->assertSame(3, $result['count']);
        $this->assertSame(20.0, $result['average']);
        $this->assertSame(10.0, $result['minimum']);
        $this->assertSame(30.0, $result['maximum']);
        $this->assertSame(60.0, $result['sum']);
    }

    public function testGetAggregatedWithNoDataReturnsZeroedShape(): void
    {
        $result = $this->model->getAggregated('never_recorded_' . uniqid('', true), 'week');

        $this->assertSame(0, $result['count']);
        $this->assertSame(0.0, $result['average']);
        $this->assertSame(0.0, $result['minimum']);
        $this->assertSame(0.0, $result['maximum']);
        $this->assertSame(0.0, $result['sum']);
    }

    public function testGetAggregatedSupportsAllPeriodBuckets(): void
    {
        $name = 'period_metric_' . uniqid('', true);
        $this->model->record($name, 5.0);

        foreach (['hour', 'day', 'week', 'month', 'unknown-defaults-to-day'] as $period) {
            $result = $this->model->getAggregated($name, $period);
            $this->assertSame($period, $result['period']);
            $this->assertSame(1, $result['count']);
        }
    }
}
