<?php

declare(strict_types=1);

namespace App\DTO\Request\Audit;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Audit Index Request DTO
 *
 * Validates filters and pagination for audit logs.
 */
readonly class AuditIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $per_page;
    public ?string $search;
    public ?string $action;
    public ?string $entity_type;
    public ?int $entity_id;
    public ?int $user_id;
    public ?string $result;
    public ?string $severity;
    public ?string $request_id;
    public string $sort;

    public function rules(): array
    {
        return [
            'page'       => 'permit_empty|is_natural_no_zero',
            'per_page'    => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'     => 'permit_empty|string|max_length[100]',
            'action'      => 'permit_empty|string|max_length[50]',
            'entity_type' => 'permit_empty|string|max_length[50]',
            'entity_id'   => 'permit_empty|is_natural_no_zero',
            'user_id'     => 'permit_empty|is_natural_no_zero',
            'result'      => 'permit_empty|in_list[success,failure,denied]',
            'severity'    => 'permit_empty|in_list[info,warning,critical]',
            'request_id'  => 'permit_empty|string|max_length[64]',
            'sort'        => 'permit_empty|max_length[100]',
        ];
    }

    protected function map(array $data): void
    {
        $filter = is_array($data['filter'] ?? null) ? $data['filter'] : [];

        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;
        $this->action = $this->extractString($data, $filter, 'action');
        $this->entity_type = $this->extractString($data, $filter, 'entity_type');
        $this->entity_id = $this->extractInt($data, $filter, 'entity_id');
        $this->user_id = $this->extractInt($data, $filter, 'user_id');
        $this->result = $this->extractString($data, $filter, 'result');
        $this->severity = $this->extractString($data, $filter, 'severity');
        $this->request_id = $this->extractString($data, $filter, 'request_id');
        $this->sort = (string) ($data['sort'] ?? '');
    }

    public function toArray(): array
    {
        $data = [
            'page'    => $this->page,
            'per_page' => $this->per_page,
            'search'  => $this->search,
            'sort'    => $this->sort,
        ];

        if ($this->action) {
            $data['filter']['action'] = ['eq' => $this->action];
        }
        if ($this->entity_type) {
            $data['filter']['entity_type'] = ['eq' => $this->entity_type];
        }
        if ($this->entity_id) {
            $data['filter']['entity_id']   = ['eq' => $this->entity_id];
        }
        if ($this->user_id) {
            $data['filter']['user_id']     = ['eq' => $this->user_id];
        }
        if ($this->result) {
            $data['filter']['result'] = ['eq' => $this->result];
        }
        if ($this->severity) {
            $data['filter']['severity'] = ['eq' => $this->severity];
        }
        if ($this->request_id) {
            $data['filter']['request_id'] = ['eq' => $this->request_id];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filter
     */
    private function extractString(array $data, array $filter, string $key): ?string
    {
        $value = $data[$key] ?? $filter[$key] ?? null;
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filter
     */
    private function extractInt(array $data, array $filter, string $key): ?int
    {
        $value = $data[$key] ?? $filter[$key] ?? null;
        if (! is_scalar($value) || ! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
