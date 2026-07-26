<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/service-token',
    tags: ['Authentication'],
    summary: 'Issue a service (machine-to-machine) JWT',
    description: 'OAuth client_credentials-style endpoint. The calling domain application authenticates with `X-App-Key` and receives a short-lived JWT whose `sub` is `service:<app_code>` and whose `scope` is the application\'s permission set. No request body is required.',
    security: [['appKeyAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Service token issued',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/ServiceTokenResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Missing X-App-Key header', ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'X-App-Key invalid, inactive, or not bound to an application'),
        new OA\Response(response: 404, description: 'The application bound to the API key has been deleted'),
        new OA\Response(response: 429, description: 'Rate limit exceeded'),
    ]
)]
class ServiceTokenEndpoints
{
}
