<?php

declare(strict_types=1);

namespace App\Documentation\Metrics;

use OpenApi\Attributes as OA;

/**
 * Metrics Schemas
 */
#[OA\Schema(
    schema: 'MetricsRequestStats',
    title: 'Metrics Request Stats',
    description: 'Aggregated request statistics for a time period',
    properties: [
        new OA\Property(property: 'period', type: 'string', example: '24h'),
        new OA\Property(property: 'since', type: 'string', example: '2026-02-26 12:00:00'),
        new OA\Property(property: 'total_requests', type: 'integer', example: 1240),
        new OA\Property(property: 'successful_requests', type: 'integer', example: 1180),
        new OA\Property(property: 'failed_requests', type: 'integer', example: 60),
        new OA\Property(property: 'avg_response_time_ms', type: 'number', example: 145.2),
        new OA\Property(property: 'p95_response_time_ms', type: 'number', example: 420),
        new OA\Property(property: 'p99_response_time_ms', type: 'number', example: 980),
        new OA\Property(property: 'error_rate_percent', type: 'number', example: 4.84),
        new OA\Property(property: 'availability_percent', type: 'number', example: 95.16),
        new OA\Property(
            property: 'status_code_breakdown',
            type: 'object',
            properties: [
                new OA\Property(property: '2xx', type: 'integer', example: 1100),
                new OA\Property(property: '3xx', type: 'integer', example: 80),
                new OA\Property(property: '4xx', type: 'integer', example: 40),
                new OA\Property(property: '5xx', type: 'integer', example: 20),
            ]
        ),
        new OA\Property(
            property: 'slo',
            type: 'object',
            properties: [
                new OA\Property(property: 'p95_target_ms', type: 'integer', example: 500),
                new OA\Property(property: 'p95_target_met', type: 'boolean', example: true),
            ]
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'MetricsSlowRequest',
    title: 'Slow Request',
    description: 'Individual slow request entry',
    properties: [
        new OA\Property(property: 'method', type: 'string', example: 'GET'),
        new OA\Property(property: 'uri', type: 'string', example: '/api/v1/users'),
        new OA\Property(property: 'response_time', type: 'integer', example: 1200),
        new OA\Property(property: 'created_at', type: 'string', example: '2026-02-26 12:00:00'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'MetricsPayload',
    title: 'Metrics Payload',
    description: 'Arbitrary metrics payload (object or array)',
    oneOf: [
        new OA\Schema(type: 'object', additionalProperties: true),
        new OA\Schema(type: 'array', items: new OA\Items(type: 'object')),
    ]
)]
#[OA\Schema(
    schema: 'MetricRecord',
    title: 'Metric Record',
    description: 'Recorded metric acknowledgement',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'api.latency'),
    ],
    type: 'object'
)]
class MetricsSchema
{
}
