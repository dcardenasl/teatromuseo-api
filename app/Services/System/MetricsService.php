<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTO\Response\Metrics\MetricsPayloadResponseDTO;
use App\Models\MetricModel;
use App\Models\RequestLogModel;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * Modernized Metrics Service
 *
 * Handles aggregation and recording of system performance metrics.
 */
readonly class MetricsService implements \App\Interfaces\System\MetricsServiceInterface
{
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    public function __construct(
        protected RequestLogModel $requestLogModel,
        protected MetricModel $metricModel,
        protected int $defaultSlowQueryThreshold = 1000,
        protected int $defaultP95TargetMs = 500
    ) {
    }

    /**
     * Get system performance overview
     */
    public function getOverview(DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\DTO\Response\Metrics\MetricsOverviewResponseDTO
    {
        /** @var \App\DTO\Request\Metrics\MetricsQueryRequestDTO $request */
        $period = $request->period;
        $requestStats = $this->requestLogModel->getStats($period);

        return \App\DTO\Response\Metrics\MetricsOverviewResponseDTO::fromArray([
            'request_stats' => $requestStats,
            'slow_requests' => $this->requestLogModel->getSlowRequests(
                $this->defaultSlowQueryThreshold,
                5,
                $period,
            ),
            'slo' => [
                'availability_percent' => $requestStats['availability_percent'] ?? 100,
                'error_rate_percent'   => $requestStats['error_rate_percent'] ?? 0,
                'p95_response_time_ms' => $requestStats['p95_response_time_ms'] ?? 0,
                'p99_response_time_ms' => $requestStats['p99_response_time_ms'] ?? 0,
                'p95_target_ms'        => $requestStats['slo']['p95_target_ms'] ?? $this->defaultP95TargetMs,
                'p95_target_met'       => $requestStats['slo']['p95_target_met'] ?? true,
            ],
        ]);
    }

    public function getRequestStats(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Metrics\MetricsQueryRequestDTO $request */
        return MetricsPayloadResponseDTO::fromArray(
            $this->requestLogModel->getStats($request->period)
        );
    }

    /**
     * Get time-bucketed request/error/latency series for trend charts.
     */
    public function getTimeseries(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Metrics\MetricsQueryRequestDTO $request */
        return MetricsPayloadResponseDTO::fromArray(
            $this->requestLogModel->getTimeseries($request->period)
        );
    }

    /**
     * Get slow requests with configurable threshold and limit.
     */
    public function getSlowRequests(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Metrics\SlowRequestsQueryRequestDTO $request */
        return MetricsPayloadResponseDTO::fromArray(
            $this->requestLogModel->getSlowRequests($request->threshold, $request->limit)
        );
    }

    public function recordFeatureToggle(string $feature, bool $enabled): void
    {
        $this->metricModel->record(
            'feature_toggle',
            $enabled ? 1.0 : 0.0,
            [
                'feature' => $feature,
                'enabled' => $enabled ? '1' : '0',
            ]
        );
    }

    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request, ?SecurityContext $context = null): OperationResult
    {
        $recorded = (bool) $this->metricModel->record($request->name, $request->value, $request->tags);

        if (!$recorded) {
            return OperationResult::error(
                message: lang('Api.requestFailed'),
                errors: ['metric' => lang('Api.requestFailed')]
            );
        }

        return OperationResult::success(
            data: ['name' => $request->name],
            message: lang('Metrics.recordedSuccessfully')
        );
    }

    public function getCustomMetric(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Metrics\CustomMetricQueryRequestDTO $request */
        $payload = $request->aggregate
            ? $this->metricModel->getAggregated($request->name, $request->period)
            : $this->metricModel->getByName($request->name, $request->period);

        return MetricsPayloadResponseDTO::fromArray($payload);
    }
}
