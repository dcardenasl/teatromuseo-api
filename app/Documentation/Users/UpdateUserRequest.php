<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * Update User Request Body
 *
 * Request schema for updating an existing user.
 * All fields are optional - only include fields to update.
 */
#[OA\RequestBody(
    request: 'UpdateUserRequest',
    description: 'Data for updating a user',
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'first_name',
                type: 'string',
                description: 'User first name',
                example: 'John'
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
                description: 'Updated email address',
                example: 'updated@example.com'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'Updated password',
                example: 'NewPassword123'
            ),
            new OA\Property(
                property: 'role',
                type: 'string',
                description: 'Updated role (user, admin, superadmin)',
                example: 'user'
            ),
        ]
    )
)]
class UpdateUserRequest
{
}
