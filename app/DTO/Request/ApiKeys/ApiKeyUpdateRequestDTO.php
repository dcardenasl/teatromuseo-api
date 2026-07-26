<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Api Key Update Request DTO
 */
readonly class ApiKeyUpdateRequestDTO extends BaseRequestDTO
{
    public ?string $name;
    public ?int $is_active;
    public ?int $rate_limit_requests;
    public ?int $rate_limit_window;
    public ?int $user_rate_limit;
    public ?int $ip_rate_limit;

    public function rules(): array
    {
        return [
            'name'                => 'permit_empty|string|max_length[100]',
            'is_active'           => 'permit_empty|in_list[0,1]',
            'rate_limit_requests' => 'permit_empty|is_natural_no_zero',
            'rate_limit_window'   => 'permit_empty|is_natural_no_zero',
            'user_rate_limit'     => 'permit_empty|is_natural_no_zero',
            'ip_rate_limit'       => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = isset($data['name']) ? trim((string) $data['name']) : null;
        $this->is_active = isset($data['is_active']) ? (int) (bool) $data['is_active'] : null;
        $this->rate_limit_requests = isset($data['rate_limit_requests']) ? (int) $data['rate_limit_requests'] : null;
        $this->rate_limit_window = isset($data['rate_limit_window']) ? (int) $data['rate_limit_window'] : null;
        $this->user_rate_limit = isset($data['user_rate_limit']) ? (int) $data['user_rate_limit'] : null;
        $this->ip_rate_limit = isset($data['ip_rate_limit']) ? (int) $data['ip_rate_limit'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name'                => $this->name,
            'is_active'           => $this->is_active,
            'rate_limit_requests' => $this->rate_limit_requests,
            'rate_limit_window'   => $this->rate_limit_window,
            'user_rate_limit'     => $this->user_rate_limit,
            'ip_rate_limit'       => $this->ip_rate_limit,
        ], fn ($v) => $v !== null);
    }
}
