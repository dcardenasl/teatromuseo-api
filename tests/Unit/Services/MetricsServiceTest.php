<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\MetricModel;
use App\Models\RequestLogModel;
use App\Services\System\MetricsService;
use CodeIgniter\Test\CIUnitTestCase;

class MetricsServiceTest extends CIUnitTestCase
{
    public function testRecordFeatureToggleStoresMetric(): void
    {
        $mockMetricModel = $this->createMock(MetricModel::class);
        $mockMetricModel
            ->expects($this->once())
            ->method('record')
            ->with(
                'feature_toggle',
                1.0,
                ['feature' => 'metrics', 'enabled' => '1']
            );

        $service = new MetricsService(
            $this->createMock(RequestLogModel::class),
            $mockMetricModel
        );

        $service->recordFeatureToggle('metrics', true);
    }
}
