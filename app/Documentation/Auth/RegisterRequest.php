<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

/**
 * Register Request Body
 *
 * Request schema for new user registration.
 * Creates new user account with email, password, and required names.
 */
#[OA\RequestBody(
    request: 'RegisterRequest',
    description: 'New user registration data',
    required: true,
    content: new OA\JsonContent(
        required: ['email', 'password', 'first_name', 'last_name'],
        properties: [
            new OA\Property(
                property: 'first_name',
                type: 'string',
                description: 'User first name',
                example: 'Jane'
            ),
            new OA\Property(
                property: 'last_name',
                type: 'string',
                description: 'User last name',
                example: 'Doe'
            ),
            new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                description: 'User email address',
                example: 'newuser@example.com'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'Password - Minimum 8 characters, must contain uppercase, lowercase, and number',
                example: 'Password123'
            ),
        ]
    )
)]
class RegisterRequest
{
}
