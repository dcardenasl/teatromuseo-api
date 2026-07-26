<?php

declare(strict_types=1);

namespace App\Documentation\Iam;

use OpenApi\Attributes as OA;

/**
 * OpenAPI definitions for Role endpoints.
 *
 * @OA\Tag(name="Iam", description="Iam management")
 */
class RoleEndpoints
{
    #[OA\Get(
        path: '/api/v1/roles',
        tags: ['Iam'],
        summary: 'List Roles',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/RoleResponse')
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/api/v1/roles',
        tags: ['Iam'],
        summary: 'Create new Role',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RoleCreateRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/api/v1/roles/{id}',
        tags: ['Iam'],
        summary: 'Get Role by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(ref: '#/components/schemas/RoleResponse')
            ),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show(): void
    {
    }

    #[OA\Put(
        path: '/api/v1/roles/{id}',
        tags: ['Iam'],
        summary: 'Update existing Role',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RoleUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/RoleResponse')
            ),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(): void
    {
    }

    #[OA\Delete(
        path: '/api/v1/roles/{id}',
        tags: ['Iam'],
        summary: 'Delete Role by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function delete(): void
    {
    }
}
