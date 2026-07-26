<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserPermissionsResponse',
    title: 'User Permissions Response',
    required: ['user_id', 'application', 'permissions']
)]
readonly class UserPermissionsResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        #[OA\Property(description: 'User identifier', example: 42)]
        public int $user_id,
        #[OA\Property(
            property: 'application',
            description: 'Summary of the application the permissions are scoped to',
            type: 'object',
            required: ['id', 'code', 'name'],
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'code', type: 'string', example: 'self'),
                new OA\Property(property: 'name', type: 'string', example: 'Self'),
            ]
        )]
        public ApplicationSummary $application,
        #[OA\Property(
            description: 'Sorted, deduplicated effective permission codes for the user within the application',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'users.read')
        )]
        public array $permissions,
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id'     => $this->user_id,
            'application' => $this->application->toArray(),
            'permissions' => $this->permissions,
        ];
    }
}
