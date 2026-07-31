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

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * NOT NULL columns (application_id, code, resource, action) never
     * accept an explicit null — treated the same as omitting the field.
     * description is the only nullable column: an explicit null preserves
     * through to toArray() and actually clears it — the bug this fixes is
     * array_filter() silently dropping every null, which made it
     * impossible to ever clear description via update.
     */
    protected function map(array $data): void
    {
        $this->application_id = array_key_exists('application_id', $data) && $data['application_id'] !== null && $data['application_id'] !== '' ? (int) $data['application_id'] : null;
        $this->code = array_key_exists('code', $data) && $data['code'] !== null ? (string) $data['code'] : null;
        $this->resource = array_key_exists('resource', $data) && $data['resource'] !== null ? (string) $data['resource'] : null;
        $this->action = array_key_exists('action', $data) && $data['action'] !== null ? (string) $data['action'] : null;
        $this->description = array_key_exists('description', $data) && $data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null;

        $mappedFields = [];
        if ($this->application_id !== null) {
            $mappedFields['application_id'] = $this->application_id;
        }
        if ($this->code !== null) {
            $mappedFields['code'] = $this->code;
        }
        if ($this->resource !== null) {
            $mappedFields['resource'] = $this->resource;
        }
        if ($this->action !== null) {
            $mappedFields['action'] = $this->action;
        }
        if (array_key_exists('description', $data)) {
            $mappedFields['description'] = $this->description;
        }

        $this->mappedFields = $mappedFields;
    }

    public function toArray(): array
    {
        return $this->mappedFields;
    }
}
