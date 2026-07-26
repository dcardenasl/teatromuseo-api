<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\FeatureToggleFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

class FeatureToggleFilterTest extends CIUnitTestCase
{
    private FeatureToggleFilter $filter;
    private \App\Interfaces\System\MetricsServiceInterface $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = $this->createMock(\App\Interfaces\System\MetricsServiceInterface::class);
        Services::injectMock('metricsService', $this->metricsService);
        $this->filter = new FeatureToggleFilter();
    }

    protected function tearDown(): void
    {
        putenv('METRICS_ENABLED');
        putenv('MONITORING_ENABLED');
        Services::reset(true);
        parent::tearDown();
    }

    public function testBeforeRecordsFeatureToggleMetric(): void
    {
        $mockMetrics = $this->createMock(\App\Interfaces\System\MetricsServiceInterface::class);
        $mockMetrics
            ->expects($this->once())
            ->method('recordFeatureToggle')
            ->with('metrics', true);

        Services::injectMock('metricsService', $mockMetrics);

        $request = $this->createMock(IncomingRequest::class);
        $this->filter->before($request, ['metrics']);
    }

    public function testBeforeAllowsRequestWhenFeatureIsEnabled(): void
    {
        putenv('METRICS_ENABLED=true');

        $request = $this->createMock(IncomingRequest::class);
        $result = $this->filter->before($request, ['metrics']);

        $this->assertInstanceOf(IncomingRequest::class, $result);
    }

    public function testBeforeAllowsRequestWhenMetricRecordingFails(): void
    {
        putenv('METRICS_ENABLED=true');

        $mockMetrics = $this->createMock(\App\Interfaces\System\MetricsServiceInterface::class);
        $mockMetrics
            ->expects($this->once())
            ->method('recordFeatureToggle')
            ->willThrowException(new \RuntimeException('metrics unavailable'));

        Services::injectMock('metricsService', $mockMetrics);

        $request = $this->createMock(IncomingRequest::class);
        $result = $this->filter->before($request, ['metrics']);

        $this->assertInstanceOf(IncomingRequest::class, $result);
    }

    public function testBeforeBlocksRequestWhenFeatureIsDisabled(): void
    {
        putenv('METRICS_ENABLED=false');

        $request = $this->createMock(IncomingRequest::class);
        $result = $this->filter->before($request, ['metrics']);

        $this->assertInstanceOf(\CodeIgniter\HTTP\ResponseInterface::class, $result);
        $this->assertSame(503, $result->getStatusCode());
    }

    public function testBeforeWithMissingArgumentAllowsRequest(): void
    {
        $request = $this->createMock(IncomingRequest::class);
        $result = $this->filter->before($request, []);

        $this->assertInstanceOf(IncomingRequest::class, $result);
    }
}
