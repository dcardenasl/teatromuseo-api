<?php

declare(strict_types=1);

namespace App\Interfaces\System;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * Metrics Service Interface
 */
interface MetricsServiceInterface
{
    /**
     * Get system performance overview
     */
    public function getOverview(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\DTO\Response\Metrics\MetricsOverviewResponseDTO;

    /**
     * Get list of slow requests
     */
    public function getSlowRequests(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Get raw request stats
     */
    public function getRequestStats(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Get time-bucketed request/error/latency series for trend charts
     */
    public function getTimeseries(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Get custom metrics by name
     */
    public function getCustomMetric(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Record a custom metric
     */
    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request, ?SecurityContext $context = null): OperationResult;

    /**
     * Record the outcome of a feature toggle evaluation
     */
    public function recordFeatureToggle(string $feature, bool $enabled): void;
}
