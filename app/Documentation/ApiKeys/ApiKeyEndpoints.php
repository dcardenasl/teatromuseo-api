<?php

declare(strict_types=1);

namespace App\Documentation\ApiKeys;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/api-keys',
    tags: ['API Keys'],
    summary: 'List API keys (admin)',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'page',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
        new OA\Parameter(
            name: 'per_page',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
        new OA\Parameter(
            name: 'search',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_active',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', enum: [0, 1])
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated list of API keys',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/ApiKeyResponse')
                    ),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 5),
                            new OA\Property(property: 'per_page', type: 'integer', example: 20),
                            new OA\Property(property: 'page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1),
                            new OA\Property(property: 'from', type: 'integer', example: 1),
                            new OA\Property(property: 'to', type: 'integer', example: 5),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — admin role required'),
    ]
)]
#[OA\Post(
    path: '/api/v1/api-keys',
    tags: ['API Keys'],
    summary: 'Create API key (admin)',
    description: 'Creates a new API key. The full raw key is returned only once in this response and cannot be retrieved again.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'Human-readable label', example: 'My Mobile App'),
                new OA\Property(property: 'rate_limit_requests', type: 'integer', description: 'Global key budget per window', example: 600),
                new OA\Property(property: 'rate_limit_window', type: 'integer', description: 'Window in seconds', example: 60),
                new OA\Property(property: 'user_rate_limit', type: 'integer', description: 'Per-user budget per window', example: 60),
                new OA\Property(property: 'ip_rate_limit', type: 'integer', description: 'Per-IP defensive limit per window', example: 200),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'API key created — raw key included once',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/ApiKeyResponse'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — admin role required'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/api-keys/{id}',
    tags: ['API Keys'],
    summary: 'Get API key by ID (admin)',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'API key details',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/ApiKeyResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — admin role required'),
        new OA\Response(response: 404, description: 'API key not found'),
    ]
)]
#[OA\Put(
    path: '/api/v1/api-keys/{id}',
    tags: ['API Keys'],
    summary: 'Update API key (admin)',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Renamed App'),
                new OA\Property(property: 'is_active', type: 'integer', enum: [0, 1], example: 0),
                new OA\Property(property: 'rate_limit_requests', type: 'integer', example: 1200),
                new OA\Property(property: 'rate_limit_window', type: 'integer', example: 60),
                new OA\Property(property: 'user_rate_limit', type: 'integer', example: 120),
                new OA\Property(property: 'ip_rate_limit', type: 'integer', example: 400),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'API key updated',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/ApiKeyResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — admin role required'),
        new OA\Response(response: 404, description: 'API key not found'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/api-keys/{id}',
    tags: ['API Keys'],
    summary: 'Delete API key (admin)',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'API key deleted',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — admin role required'),
        new OA\Response(response: 404, description: 'API key not found'),
    ]
)]
class ApiKeyEndpoints
{
}
