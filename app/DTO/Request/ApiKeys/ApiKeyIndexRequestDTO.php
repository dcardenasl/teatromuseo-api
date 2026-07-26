<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Api Key Index Request DTO
 */
readonly class ApiKeyIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $per_page;
    public ?string $search;
    public ?int $is_active;
    public string $sort;

    public function rules(): array
    {
        return [
            'page'      => 'permit_empty|is_natural_no_zero',
            'per_page'   => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'    => 'permit_empty|string|max_length[100]',
            'is_active'  => 'permit_empty|in_list[0,1]',
            'sort'      => 'permit_empty|max_length[100]',
        ];
    }

    protected function map(array $data): void
    {
        $filter = is_array($data['filter'] ?? null) ? $data['filter'] : [];

        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $search = isset($data['search']) && is_scalar($data['search'])
            ? trim((string) $data['search'])
            : null;

        if (($search === null || $search === '') && isset($filter['name']) && is_scalar($filter['name'])) {
            $nameFilter = trim((string) $filter['name']);
            $search = $nameFilter === '' ? null : $nameFilter;
        }
        $this->search = $search;

        $isActive = $data['is_active'] ?? $filter['is_active'] ?? null;
        $this->is_active = is_numeric($isActive) ? (int) $isActive : null;

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

        if ($this->is_active !== null) {
            $data['filter']['is_active'] = ['eq' => $this->is_active];
        }

        return $data;
    }
}
