<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/introspect',
    tags: ['Authentication'],
    summary: 'Introspect a JWT issued by this server',
    description: 'Validates a JWT (signature, expiration, revocation) and returns its claims. Authenticated by X-App-Key — domain apps can verify user tokens without sharing the JWT secret. Always responds 200 with `valid: true|false` unless the calling app itself is unauthorized.',
    security: [['appKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/IntrospectRequest')
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Introspection result (check `valid` for the verdict)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/IntrospectResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Missing X-App-Key header', ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'X-App-Key is invalid or inactive'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        new OA\Response(response: 429, description: 'Rate limit exceeded'),
    ]
)]
class IntrospectEndpoints
{
}
