<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Login Request DTO
 *
 * Validates credentials for user authentication.
 */
#[OA\Schema(
    schema: 'LoginRequest',
    title: 'Login Request',
    description: 'User credentials for authentication',
    required: ['email', 'password']
)]
readonly class LoginRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'User registered email', example: 'user@example.com')]
    public string $email;

    #[OA\Property(description: 'User password', example: 'P@ssw0rd123!', format: 'password')]
    public string $password;

    public function rules(): array
    {
        return [
            'email'    => 'required|valid_email|max_length[255]',
            'password' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) $data['email']));
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return [
            'email'    => $this->email,
            'password' => $this->password,
        ];
    }
}
