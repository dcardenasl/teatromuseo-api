<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PermissionUpdateRequest')]
readonly class PermissionUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'application_id', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'code', type: 'string', nullable: true)]
    public ?string $code;
    #[OA\Property(description: 'resource', type: 'string', nullable: true)]
    public ?string $resource;
    #[OA\Property(description: 'action', type: 'string', nullable: true)]
    public ?string $action;
    #[OA\Property(description: 'description', type: 'string', nullable: true)]
    public ?string $description;

    public function rules(): array
    {
        return [
            'application_id' => 'permit_empty|integer',
            'code' => 'permit_empty|string|max_length[100]',
            'resource' => 'permit_empty|string|max_length[50]',
            'action' => 'permit_empty|string|max_length[50]',
            'description' => 'permit_empty|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->code = $data['code'] ?? null;
        $this->resource = $data['resource'] ?? null;
        $this->action = $data['action'] ?? null;
        $this->description = $data['description'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'application_id' => $this->application_id,
            'code' => $this->code,
            'resource' => $this->resource,
            'action' => $this->action,
            'description' => $this->description,
        ], fn ($v) => $v !== null);
    }
}
