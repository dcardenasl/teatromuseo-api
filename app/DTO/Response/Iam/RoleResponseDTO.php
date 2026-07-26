<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RoleResponse',
    title: 'Role Response',
    required: ["id","code","name","is_system"]
)]
readonly class RoleResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'Application id; null for global roles', type: 'integer', nullable: true)]
        public ?int $application_id,
        #[OA\Property(description: 'Role code', type: 'string')]
        public string $code,
        #[OA\Property(description: 'Display name', type: 'string')]
        public string $name,
        #[OA\Property(description: 'Description', type: 'string', nullable: true)]
        public ?string $description,
        #[OA\Property(description: 'System role flag', type: 'boolean')]
        public bool $is_system,
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
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
