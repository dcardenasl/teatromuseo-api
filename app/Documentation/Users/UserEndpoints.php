<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/users',
    tags: ['Users'],
    summary: 'List users (admin)',
    description: 'Lists users except accounts with role superadmin. Requires admin role.',
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
            name: 'role',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['user', 'admin', 'superadmin'])
        ),
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'pending_approval', 'invited'])
        ),
        new OA\Parameter(
            name: 'order_by',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['id', 'email', 'created_at', 'role', 'status', 'first_name', 'last_name'])
        ),
        new OA\Parameter(
            name: 'order_dir',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC', 'asc', 'desc'])
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated user list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/UserResponse')
                    ),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 100),
                            new OA\Property(property: 'per_page', type: 'integer', example: 20),
                            new OA\Property(property: 'page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 5),
                            new OA\Property(property: 'from', type: 'integer', example: 1),
                            new OA\Property(property: 'to', type: 'integer', example: 20),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — requires admin role'),
    ]
)]
#[OA\Get(
    path: '/api/v1/users/{id}',
    tags: ['Users'],
    summary: 'Get user by ID',
    description: 'Get details of a specific user. Regular users can only access their own profile.',
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
            description: 'User details',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — cannot access another users profile'),
        new OA\Response(response: 404, description: 'User not found'),
    ]
)]
#[OA\Post(
    path: '/api/v1/users',
    tags: ['Users'],
    summary: 'Create user (admin/superadmin)',
    description: 'Admins can create only role=user. Creating admin/superadmin requires superadmin. The account is created as active and an invitation email is sent to set the password.',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        ref: '#/components/requestBodies/CreateUserRequest'
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'User created',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — insufficient role permissions'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Put(
    path: '/api/v1/users/{id}',
    tags: ['Users'],
    summary: 'Update user (admin/superadmin)',
    description: 'Admins can only update users with role=user and cannot promote to admin/superadmin.',
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
        ref: '#/components/requestBodies/UpdateUserRequest'
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'User updated',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — insufficient role permissions'),
        new OA\Response(response: 404, description: 'User not found'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/users/{id}/approve',
    tags: ['Users'],
    summary: 'Approve user (admin)',
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
            description: 'User approved',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResponse'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'User not found'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/users/{id}',
    tags: ['Users'],
    summary: 'Delete user (admin/superadmin)',
    description: 'Admins can delete only users with role=user.',
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
            description: 'User deleted',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Forbidden — insufficient role permissions'),
        new OA\Response(response: 404, description: 'User not found'),
    ]
)]
class UserEndpoints
{
}
