<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Refresh Token Request DTO
 */
readonly class RefreshTokenRequestDTO extends BaseRequestDTO
{
    public string $refresh_token;

    public function rules(): array
    {
        return [
            'refresh_token' => 'required|string|min_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->refresh_token = (string) ($data['refresh_token'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'refresh_token' => $this->refresh_token,
        ];
    }
}
