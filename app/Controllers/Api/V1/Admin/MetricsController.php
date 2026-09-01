<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\DTO\Request\Metrics\CustomMetricQueryRequestDTO;
use App\DTO\Request\Metrics\MetricsQueryRequestDTO;
use App\DTO\Request\Metrics\RecordMetricRequestDTO;
use App\DTO\Request\Metrics\SlowRequestsQueryRequestDTO;
use App\Interfaces\System\MetricsServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Modernized Metrics Controller
 *
 * Provides administrative access to system performance and usage metrics.
 */
class MetricsController extends ApiController
{
    protected MetricsServiceInterface $metricsService;

    protected function resolveDefaultService(): object
    {
        $this->metricsService = Services::metricsService();

        return $this->metricsService;
    }

    /**
     * Map record to 201 Created status
     */
    protected array $statusCodes = [
        'record' => 201,
    ];

    /**
     * Get system metrics overview
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('getOverview', MetricsQueryRequestDTO::class);
    }

    /**
     * Get request statistics
     */
    public function requests(): ResponseInterface
    {
        return $this->handleRequest('getRequestStats', MetricsQueryRequestDTO::class);
    }

    /**
     * Get list of slow requests
     */
    public function slowRequests(): ResponseInterface
    {
        return $this->handleRequest('getSlowRequests', SlowRequestsQueryRequestDTO::class);
    }

    /**
     * Get time-bucketed request/error/latency series for trend charts
     */
    public function timeseries(): ResponseInterface
    {
        return $this->handleRequest('getTimeseries', MetricsQueryRequestDTO::class);
    }

    /**
     * Return the summary and trend series for one validated period.
     *
     * The Hub remains the owner of both metric read models. This endpoint
     * removes the Admin's transport fan-out without pretending the two
     * aggregates are the same metric or sharing their failure semantics.
     */
    public function workspace(): ResponseInterface
    {
        return $this->handleRequest(function (MetricsQueryRequestDTO $request, ?\dcardenasl\Ci4ApiCore\Dto\SecurityContext $context): array {
            return [
                'summary' => $this->metricsService->getOverview($request, $context)->toArray(),
                'timeseries' => $this->metricsService->getTimeseries($request, $context)->toArray(),
            ];
        }, MetricsQueryRequestDTO::class);
    }


    /**
     * Get custom metrics by name
     */
    public function custom(string $name): ResponseInterface
    {
        return $this->handleRequest(
            'getCustomMetric',
            CustomMetricQueryRequestDTO::class,
            ['name' => $name]
        );
    }

    /**
     * Record a new custom metric
     */
    public function record(): ResponseInterface
    {
        return $this->handleRequest('record', RecordMetricRequestDTO::class);
    }
}
