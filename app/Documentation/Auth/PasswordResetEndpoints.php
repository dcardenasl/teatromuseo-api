<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/forgot-password',
    tags: ['Authentication'],
    summary: 'Send password reset link',
    description: 'Always returns a generic success response for valid emails. For soft-deleted accounts, this may trigger a reactivation request that requires admin approval.',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Generic success response (reset queued or reactivation requested)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/auth/validate-reset-token',
    tags: ['Authentication'],
    summary: 'Validate password reset token',
    parameters: [
        new OA\Parameter(
            name: 'token',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'email',
            in: 'query',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'email')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Token is valid',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        new OA\Response(response: 404, description: 'Invalid token'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/reset-password',
    tags: ['Authentication'],
    summary: 'Reset password using token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'email', 'password'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Password reset successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        new OA\Response(response: 404, description: 'Invalid token'),
    ]
)]
class PasswordResetEndpoints
{
}
