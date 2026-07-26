<?php

declare(strict_types=1);

namespace App\Documentation\Files;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/files',
    tags: ['Files'],
    summary: 'List files for current user',
    description: 'By default only non-trashed files are returned. Use `trashed=only` to list the trash bin, or `trashed=with` to include both.',
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
            name: 'trashed',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['without', 'only', 'with'], default: 'without')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'File list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/FileResponse')
                    ),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 45),
                            new OA\Property(property: 'per_page', type: 'integer', example: 20),
                            new OA\Property(property: 'page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 3),
                            new OA\Property(property: 'from', type: 'integer', example: 1),
                            new OA\Property(property: 'to', type: 'integer', example: 20),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/upload',
    tags: ['Files'],
    summary: 'Upload a file',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ],
                type: 'object'
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'File uploaded',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        ref: '#/components/schemas/FileResponse'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/files/{id}',
    tags: ['Files'],
    summary: 'Download or get file metadata',
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
            description: 'File metadata (or file download for local storage)',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'status', type: 'string', example: 'success'),
                            new OA\Property(
                                property: 'data',
                                ref: '#/components/schemas/FileDownloadResponse'
                            ),
                        ],
                        type: 'object'
                    )
                ),
                new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                ),
            ]
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/files/{id}',
    tags: ['Files'],
    summary: 'Move file to trash (soft-delete)',
    description: 'Sets `deleted_at` so the file disappears from default listings. Storage bytes are preserved so the file can be restored. To purge permanently, call `DELETE /files/{id}/force` after.',
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
            description: 'File moved to trash',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 400, description: 'File already trashed'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/{id}/replace',
    tags: ['Files'],
    summary: 'Replace a file\'s binary content',
    description: 'Uploads a new file to replace the existing one. Preserves the record ID and all references. Deletes the old storage object.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ],
                type: 'object'
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'File replaced',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/FileResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 400, description: 'File is trashed'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Patch(
    path: '/api/v1/files/{id}',
    tags: ['Files'],
    summary: 'Update file metadata',
    description: 'Update editable metadata fields. At least one field must be provided. Does not affect the stored binary or variants.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'original_name', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'alt_text', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'caption', type: 'string', nullable: true),
                new OA\Property(property: 'credit', type: 'string', maxLength: 255, nullable: true),
                new OA\Property(property: 'category', type: 'string', enum: ['document', 'image', 'video', 'audio'], nullable: true),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'File metadata updated',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/FileResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 400, description: 'No metadata fields provided'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/{id}/restore',
    tags: ['Files'],
    summary: 'Restore a trashed file',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'File restored'),
        new OA\Response(response: 400, description: 'File is not in the trash'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/files/{id}/force',
    tags: ['Files'],
    summary: 'Permanently delete a trashed file',
    description: 'Removes storage bytes AND the DB row. Only valid for files already in the trash — call `DELETE /files/{id}` first.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'File permanently deleted'),
        new OA\Response(response: 400, description: 'File is not in the trash'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Get(
    path: '/api/v1/files/{id}/info',
    tags: ['Files'],
    summary: 'Get file metadata (JSON)',
    description: 'Returns the JSON metadata for a single file. Unlike the main GET /{id} endpoint, this never triggers a binary download.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'File metadata',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/FileResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Get(
    path: '/api/v1/files/{id}/usages',
    tags: ['Files'],
    summary: 'List resources referencing this file',
    description: 'Returns a list of business resources (e.g. Products, Categories, Posts) that have a foreign key reference to this file.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Usage list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'resource', type: 'string', example: 'products'),
                                new OA\Property(property: 'resource_id', type: 'integer', example: 45),
                                new OA\Property(property: 'label', type: 'string', example: 'Logo Apple.png', nullable: true),
                                new OA\Property(property: 'role', type: 'string', example: 'cover'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/{id}/regenerate-variants',
    tags: ['Files'],
    summary: 'Regenerate image variants',
    description: 'Deletes existing image variants and recreates them from the stored original file. Only valid for processable image MIME types.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Variants regenerated',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(
                            properties: [
                                new OA\Property(property: 'path', type: 'string'),
                                new OA\Property(property: 'url', type: 'string'),
                                new OA\Property(property: 'width', type: 'integer'),
                                new OA\Property(property: 'height', type: 'integer'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 400, description: 'File is not a processable image'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/bulk-delete',
    tags: ['Files'],
    summary: 'Bulk move files to trash',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [
                new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer')),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Per-item bulk outcome',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'ok', type: 'boolean'),
                                new OA\Property(property: 'error', type: 'string', nullable: true),
                            ],
                            type: 'object'
                        )
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
    path: '/api/v1/files/bulk-restore',
    tags: ['Files'],
    summary: 'Bulk restore trashed files',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Per-item bulk outcome'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/bulk-force-delete',
    tags: ['Files'],
    summary: 'Bulk permanently delete trashed files',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['ids'],
            properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Per-item bulk outcome'),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
class FileEndpoints
{
}
