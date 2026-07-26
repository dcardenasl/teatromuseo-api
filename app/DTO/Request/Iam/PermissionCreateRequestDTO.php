<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PermissionCreateRequest')]
readonly class PermissionCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'application_id', type: 'integer')]
    public int $application_id;
    #[OA\Property(description: 'code', type: 'string')]
    public string $code;
    #[OA\Property(description: 'resource', type: 'string')]
    public string $resource;
    #[OA\Property(description: 'action', type: 'string')]
    public string $action;
    #[OA\Property(description: 'description', type: 'string')]
    public string $description;

    public function rules(): array
    {
        return [
            'application_id' => 'permit_empty|integer',
            'code' => 'required|string|max_length[100]',
            'resource' => 'required|string|max_length[50]',
            'action' => 'required|string|max_length[50]',
            'description' => 'permit_empty|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = (int) ($data['application_id'] ?? 0);
        $this->code = (string) ($data['code'] ?? '');
        $this->resource = (string) ($data['resource'] ?? '');
        $this->action = (string) ($data['action'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'application_id' => $this->application_id,
            'code' => $this->code,
            'resource' => $this->resource,
            'action' => $this->action,
            'description' => $this->description,
        ];
    }
}
