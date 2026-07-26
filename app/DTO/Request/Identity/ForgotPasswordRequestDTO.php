<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Forgot Password Request DTO
 *
 * Validates the email address for password recovery.
 */
readonly class ForgotPasswordRequestDTO extends BaseRequestDTO
{
    public string $email;
    public ?string $locale;

    public function rules(): array
    {
        return [
            'email' => 'required|valid_email|max_length[255]',
            'locale' => 'permit_empty|string|max_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) ($data['email'] ?? '')));
        $locale = isset($data['locale']) ? strtolower(trim((string) $data['locale'])) : '';
        $this->locale = $locale !== '' ? $locale : null;
    }

    public function toArray(): array
    {
        $payload = ['email' => $this->email];
        if ($this->locale !== null) {
            $payload['locale'] = $this->locale;
        }

        return $payload;
    }
}
