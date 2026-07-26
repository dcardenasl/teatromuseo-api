<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Password Reset Token Validation DTO
 */
readonly class PasswordResetTokenValidationDTO extends BaseRequestDTO
{
    public string $email;
    public string $token;

    public function rules(): array
    {
        return [
            'email' => 'required|valid_email',
            'token' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) ($data['email'] ?? '')));
        $this->token = (string) ($data['token'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'token' => $this->token,
        ];
    }
}
