<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;

/**
 * File Index Request DTO
 *
 * Validates pagination and ensures user_id is present for security.
 */
readonly class FileIndexRequestDTO extends BaseRequestDTO
{
    public const TRASHED_WITHOUT = 'without';
    public const TRASHED_ONLY    = 'only';
    public const TRASHED_WITH    = 'with';

    public int $page;
    public int $per_page;
    public int $user_id;
    public ?string $search;
    public string $sort;
    /** @var array<string, mixed> */
    public array $filter;
    public string $trashed;

    public function rules(): array
    {
        return [
            'page'    => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'  => 'permit_empty|string|max_length[100]',
            'sort'    => 'permit_empty|max_length[100]',
            'trashed' => 'permit_empty|in_list[without,only,with]',
        ];
    }

    protected function map(array $data): void
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->user_id = (int) $data['user_id'];
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;
        $this->sort = (string) ($data['sort'] ?? '');
        $this->filter = isset($data['filter']) && is_array($data['filter']) ? $data['filter'] : [];
        $trashed = (string) ($data['trashed'] ?? self::TRASHED_WITHOUT);
        $this->trashed = in_array($trashed, [self::TRASHED_WITHOUT, self::TRASHED_ONLY, self::TRASHED_WITH], true)
            ? $trashed
            : self::TRASHED_WITHOUT;
    }

    public function toArray(): array
    {
        return [
            'page'    => $this->page,
            'per_page' => $this->per_page,
            'user_id'  => $this->user_id,
            'search'  => $this->search,
            'sort'    => $this->sort,
            'filter'  => $this->filter,
            'trashed' => $this->trashed,
        ];
    }
}
