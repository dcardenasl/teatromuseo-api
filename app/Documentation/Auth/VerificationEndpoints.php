<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/verify-email',
    tags: ['Authentication'],
    summary: 'Verify email with token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Email verified',
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
        new OA\Response(response: 400, description: 'Invalid or expired token'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/resend-verification',
    tags: ['Authentication'],
    summary: 'Resend verification email',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Verification email sent',
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
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
class VerificationEndpoints
{
}
