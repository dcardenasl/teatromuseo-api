<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * User Index Request DTO
 *
 * Handles filtering, sorting, and pagination for user listing.
 */
#[OA\Schema(
    schema: 'UserIndexRequest',
    title: 'User Index Request',
    description: 'Parameters for filtering and paginating the user list'
)]
readonly class UserIndexRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Current page number', default: 1, example: 1)]
    public int $page;

    #[OA\Property(description: 'Items per page', default: 20, example: 20, maximum: 100)]
    public int $per_page;

    #[OA\Property(description: 'Search term for email, first name or last name', nullable: true, example: 'john')]
    public ?string $search;

    #[OA\Property(description: 'Filter by account status', enum: ['active', 'inactive', 'pending_approval', 'invited'], nullable: true)]
    public ?string $status;

    #[OA\Property(description: 'Sort field and direction', default: '', example: 'created_at or -created_at', nullable: true)]
    public string $sort;

    public string $projection;

    public function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
            'status'   => 'permit_empty|in_list[active,inactive,pending_approval,invited]',
            'sort'     => 'permit_empty|max_length[100]',
            'projection' => 'permit_empty|in_list[full,list]',
        ];
    }

    protected function map(array $data): void
    {
        $filter = is_array($data['filter'] ?? null) ? $data['filter'] : [];

        // Define default values if not provided
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;

        if ($this->search === null && isset($filter['search']) && is_scalar($filter['search'])) {
            $candidate = trim((string) $filter['search']);
            $this->search = $candidate === '' ? null : $candidate;
        }

        $this->status = $this->extractString($data, $filter, 'status');

        $this->sort = (string) ($data['sort'] ?? '');
        $this->projection = (string) ($data['projection'] ?? 'full');
    }

    public function toArray(): array
    {
        $data = [
            'page'     => $this->page,
            'per_page'  => $this->per_page,
            'search'   => $this->search,
            'sort'     => $this->sort,
            'projection' => $this->projection,
        ];

        if ($this->status) {
            $data['filter']['status'] = ['eq' => $this->status];
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
        if (is_array($value) && array_key_exists('eq', $value)) {
            $value = $value['eq'];
        }
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
