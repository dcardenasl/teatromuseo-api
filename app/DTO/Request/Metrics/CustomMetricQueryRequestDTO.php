<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Custom Metric Query Request DTO
 */
readonly class CustomMetricQueryRequestDTO extends BaseRequestDTO
{
    public string $name;
    public string $period;
    public bool $aggregate;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max_length[100]',
            'period' => 'permit_empty|in_list[1h,24h,7d,30d]',
            'aggregate' => 'permit_empty|in_list[true,false,1,0]',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
        $this->period = (string) ($data['period'] ?? '24h');
        $aggregate = strtolower((string) ($data['aggregate'] ?? 'false'));
        $this->aggregate = in_array($aggregate, ['1', 'true'], true);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'period' => $this->period,
            'aggregate' => $this->aggregate,
        ];
    }
}
