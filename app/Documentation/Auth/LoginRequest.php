<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

/**
 * Login Request Body
 *
 * Request schema for user authentication.
 * Accepts email with password.
 */
#[OA\RequestBody(
    request: 'LoginRequest',
    description: 'User login credentials',
    required: true,
    content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(
                property: 'email',
                type: 'string',
                description: 'Email address',
                example: 'user@example.com'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'User password',
                example: 'testpass123'
            ),
        ]
    )
)]
class LoginRequest
{
}
