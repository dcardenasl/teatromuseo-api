<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/refresh',
    tags: ['Authentication'],
    summary: 'Refresh access token',
    description: 'Issues a fresh access/refresh token pair and returns the canonical authenticated user (including effective permissions) so consumers can re-hydrate UI gating without an extra `/auth/me` call.',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [
                new OA\Property(
                    property: 'refresh_token',
                    type: 'string',
                    description: 'Refresh token issued at login/register'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Token refreshed',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        ref: '#/components/schemas/TokenResponse'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/revoke',
    tags: ['Authentication'],
    summary: 'Revoke current access token',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Token revoked',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string', example: 'Token revoked'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 400, description: 'Invalid token'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/revoke-all',
    tags: ['Authentication'],
    summary: 'Revoke all tokens for current user',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'All tokens revoked',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string', example: 'All tokens revoked'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
class TokenEndpoints
{
}
