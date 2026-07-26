<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Metrics Query Request DTO
 *
 * Validates the time period for metric aggregation.
 */
readonly class MetricsQueryRequestDTO extends BaseRequestDTO
{
    public string $period;

    public function rules(): array
    {
        return [
            'period' => 'permit_empty|in_list[1h,24h,7d,30d]',
        ];
    }

    protected function map(array $data): void
    {
        $this->period = $data['period'] ?? '24h';
    }

    public function toArray(): array
    {
        return [
            'period' => $this->period,
        ];
    }
}
