<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Google Login Request DTO
 *
 * Validates Google ID token for authentication.
 */
readonly class GoogleLoginRequestDTO extends BaseRequestDTO
{
    public string $id_token;
    public ?string $locale;

    public function rules(): array
    {
        return [
            'id_token' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->id_token = (string) ($data['id_token'] ?? '');
        $locale = isset($data['locale']) ? strtolower(trim((string) $data['locale'])) : '';
        $this->locale = $locale !== '' ? $locale : null;
    }

    public function toArray(): array
    {
        $payload = [
            'id_token' => $this->id_token,
        ];

        if ($this->locale !== null) {
            $payload['locale'] = $this->locale;
        }

        return $payload;
    }
}
