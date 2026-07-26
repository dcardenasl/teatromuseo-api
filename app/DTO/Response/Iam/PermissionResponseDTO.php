<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PermissionResponse',
    title: 'Permission Response',
    required: ["id","application_id","code"]
)]
readonly class PermissionResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'application_id', type: 'integer')]
        public int $application_id,
        #[OA\Property(description: 'code', type: 'string')]
        public string $code,
        #[OA\Property(description: 'resource', type: 'string')]
        public string $resource,
        #[OA\Property(description: 'action', type: 'string')]
        public string $action,
        #[OA\Property(description: 'description', type: 'string')]
        public string $description,
        #[OA\Property(property: 'application_name', description: 'Display name of the related application', type: 'string', nullable: true)]
        public ?string $application_name = null,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $createdAt = null,
        #[OA\Property(property: 'updated_at', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'application_name' => $this->application_name,
            'code' => $this->code,
            'resource' => $this->resource,
            'action' => $this->action,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
