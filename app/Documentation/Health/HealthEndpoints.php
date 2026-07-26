<?php

declare(strict_types=1);

namespace App\Documentation\Health;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/health',
    tags: ['Health'],
    summary: 'Health check',
    responses: [
        new OA\Response(
            response: 200,
            description: 'System healthy',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                    new OA\Property(property: 'timestamp', type: 'string'),
                    new OA\Property(property: 'checks', type: 'object'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 503, description: 'System unhealthy or monitoring disabled'),
    ]
)]
#[OA\Get(
    path: '/ping',
    tags: ['Health'],
    summary: 'Ping',
    responses: [
        new OA\Response(
            response: 200,
            description: 'Service reachable',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ok'),
                    new OA\Property(property: 'timestamp', type: 'string'),
                ],
                type: 'object'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/ready',
    tags: ['Health'],
    summary: 'Readiness check',
    responses: [
        new OA\Response(
            response: 200,
            description: 'Ready to serve',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ready'),
                    new OA\Property(property: 'timestamp', type: 'string'),
                    new OA\Property(property: 'database', type: 'object'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 503, description: 'Not ready'),
    ]
)]
#[OA\Get(
    path: '/live',
    tags: ['Health'],
    summary: 'Liveness check',
    responses: [
        new OA\Response(
            response: 200,
            description: 'Service alive',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'alive'),
                    new OA\Property(property: 'timestamp', type: 'string'),
                    new OA\Property(property: 'uptime_seconds', type: 'integer', example: 12345),
                ],
                type: 'object'
            )
        ),
    ]
)]
class HealthEndpoints
{
}
