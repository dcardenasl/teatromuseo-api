<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * Create User Request Body
 *
 * Request schema for creating a new user via the admin/superadmin endpoint.
 * Requires email; password is not accepted on this endpoint.
 * The account is created as active and an invitation email is sent to set the password.
 */
#[OA\RequestBody(
    request: 'CreateUserRequest',
    description: 'Data for creating a new user',
    required: true,
    content: new OA\JsonContent(
        required: ['email'],
        properties: [
            new OA\Property(
                property: 'first_name',
                type: 'string',
                description: 'First name',
                example: 'Alex'
            ),
            new OA\Property(
                property: 'last_name',
                type: 'string',
                description: 'Last name',
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
                property: 'role',
                type: 'string',
                description: 'User role (user, admin, superadmin)',
                example: 'user'
            ),
            new OA\Property(
                property: 'oauth_provider',
                type: 'string',
                description: 'Optional OAuth provider for externally managed accounts',
                example: 'google'
            ),
            new OA\Property(
                property: 'oauth_provider_id',
                type: 'string',
                description: 'Provider-specific identifier',
                example: '113337022221111122223'
            ),
            new OA\Property(
                property: 'avatar_url',
                type: 'string',
                format: 'uri',
                description: 'Optional avatar URL',
                example: 'https://example.com/avatar.png'
            ),
        ]
    )
)]
class CreateUserRequest
{
}
