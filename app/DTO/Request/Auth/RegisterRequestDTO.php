<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Register Request DTO
 *
 * Validates data for user self-registration.
 */
#[OA\Schema(
    schema: 'RegisterRequest',
    title: 'Register Request',
    description: 'User data for self-registration',
    required: ['email', 'first_name', 'last_name', 'password']
)]
readonly class RegisterRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'User email address', example: 'newuser@example.com')]
    public string $email;

    #[OA\Property(description: 'User first name', example: 'John')]
    public string $first_name;

    #[OA\Property(description: 'User last name', example: 'Doe')]
    public string $last_name;

    #[OA\Property(description: 'User password (must be strong)', example: 'P@ssw0rd123!', format: 'password')]
    public string $password;

    #[OA\Property(description: 'Preferred locale for localized emails and messages', example: 'es', nullable: true)]
    public ?string $locale;

    public function rules(): array
    {
        return [
            'email'     => 'required|valid_email_idn|max_length[255]|is_unique[users.email]',
            'first_name' => 'required|string|max_length[100]',
            'last_name'  => 'required|string|max_length[100]',
            'password'  => 'required|strong_password',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) $data['email']));
        $this->first_name = trim((string) ($data['first_name'] ?? ''));
        $this->last_name = trim((string) ($data['last_name'] ?? ''));
        $this->password = (string) $data['password'];
        $locale = isset($data['locale']) ? strtolower(trim((string) $data['locale'])) : '';
        $this->locale = $locale !== '' ? $locale : null;
    }

    public function toArray(): array
    {
        $payload = [
            'email'     => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'password'  => $this->password,
        ];

        if ($this->locale !== null) {
            $payload['locale'] = $this->locale;
        }

        return $payload;
    }
}
