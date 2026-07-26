<?php

declare(strict_types=1);

namespace App\Documentation\Metrics;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/metrics',
    tags: ['Metrics'],
    summary: 'Get metrics overview',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'period',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], example: '24h')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Metrics overview',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/MetricsOverview'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 503, description: 'Metrics disabled'),
    ]
)]
#[OA\Get(
    path: '/api/v1/metrics/requests',
    tags: ['Metrics'],
    summary: 'Get request statistics',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'period',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], example: '24h')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Request statistics',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/MetricsRequestStats'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/metrics/slow-requests',
    tags: ['Metrics'],
    summary: 'Get slow requests',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'threshold',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', example: 1000)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', example: 10)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Slow request list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/MetricsSlowRequest')
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/metrics/timeseries',
    tags: ['Metrics'],
    summary: 'Get time-bucketed request/error/latency series',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'period',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], example: '24h')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Time series data: parallel arrays of bucket labels, request counts, error counts, and average latency',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/MetricsPayloadResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/metrics/custom/{name}',
    tags: ['Metrics'],
    summary: 'Get custom metrics',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'name',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'period',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], example: '24h')
        ),
        new OA\Parameter(
            name: 'aggregate',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'boolean')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Custom metric data',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/MetricsPayload'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/metrics/record',
    tags: ['Metrics'],
    summary: 'Record a custom metric',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'value'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'value', type: 'number'),
                new OA\Property(property: 'tags', type: 'object'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Metric recorded',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/MetricRecord'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
class MetricsEndpoints
{
}
