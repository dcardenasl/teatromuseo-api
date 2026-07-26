<?php

declare(strict_types=1);

namespace App\DTO\Request\Audit;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Audit By Entity Request DTO
 */
readonly class AuditByEntityRequestDTO extends BaseRequestDTO
{
    public string $entity_type;
    public int $entity_id;

    public function rules(): array
    {
        return [
            'entity_type' => 'required|string|max_length[100]',
            'entity_id' => 'required|integer|greater_than[0]',
        ];
    }

    protected function map(array $data): void
    {
        $this->entity_type = (string) ($data['entity_type'] ?? '');
        $this->entity_id = (int) ($data['entity_id'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
        ];
    }
}
