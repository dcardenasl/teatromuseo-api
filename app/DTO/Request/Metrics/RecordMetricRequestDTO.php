<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Record Metric Request DTO
 */
readonly class RecordMetricRequestDTO extends BaseRequestDTO
{
    public string $name;
    public float $value;
    /** @var array<string, mixed> */
    public array $tags;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max_length[100]',
            'value' => 'permit_empty|decimal',
            'tags' => 'permit_empty',
        ];
    }

    public function messages(): array
    {
        return [
            'name' => lang('Metrics.nameRequired'),
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) $data['name'];
        $this->value = (float) ($data['value'] ?? 0);
        $this->tags = (array) ($data['tags'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'tags' => $this->tags,
        ];
    }
}
