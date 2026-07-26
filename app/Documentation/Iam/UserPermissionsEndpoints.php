<?php

declare(strict_types=1);

namespace App\Documentation\Iam;

use OpenApi\Attributes as OA;

/**
 * OpenAPI definition for the user effective-permissions endpoint
 * scoped by application code.
 */
class UserPermissionsEndpoints
{
    #[OA\Get(
        path: '/api/v1/iam/users/{user_id}/permissions',
        tags: ['Iam'],
        summary: "List a user's effective permissions for a specific application (by code)",
        parameters: [
            new OA\Parameter(
                name: 'user_id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 42)
            ),
            new OA\Parameter(
                name: 'app',
                in: 'query',
                required: true,
                description: 'Application code (e.g. self, blog, shop)',
                schema: new OA\Schema(type: 'string', example: 'self')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Effective permissions retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/UserPermissionsResponse'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Authentication required'),
            new OA\Response(response: 403, description: 'Caller lacks iam.superadmin-access'),
            new OA\Response(response: 404, description: 'User or application not found'),
            new OA\Response(response: 422, description: 'Missing or invalid app code'),
        ]
    )]
    public function index(): void
    {
    }
}
