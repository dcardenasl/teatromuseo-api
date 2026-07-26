<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Slow Requests Query Request DTO
 *
 * Uses `limit` (not `per_page`) on purpose: this endpoint returns the
 * top-N slowest requests above a latency threshold. There is no concept
 * of a "page 2" — the consumer asks for "the worst 10 in the window"
 * and gets a capped list. Audit B7.5 (2026-05-06) clarified this
 * distinction; see `docs/tech/pagination.md`.
 */
readonly class SlowRequestsQueryRequestDTO extends BaseRequestDTO
{
    public int $threshold;
    public int $limit;

    public function rules(): array
    {
        return [
            'threshold' => 'permit_empty|integer|greater_than_equal_to[1]',
            'limit' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
        ];
    }

    protected function map(array $data): void
    {
        $threshold = is_numeric($data['threshold'] ?? null)
            ? (int) $data['threshold']
            : config('Api')->slowQueryThreshold;

        $limit = is_numeric($data['limit'] ?? null)
            ? (int) $data['limit']
            : 10;

        $this->threshold = max(1, $threshold);
        $this->limit = min(max(1, $limit), 100);
    }

    public function toArray(): array
    {
        return [
            'threshold' => $this->threshold,
            'limit' => $this->limit,
        ];
    }
}
