<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * User Update Request DTO
 *
 * Validates data for updating an existing user.
 */
#[OA\Schema(
    schema: 'UserUpdateRequest',
    title: 'User Update Request',
    description: 'Data needed to update an existing user'
)]
readonly class UserUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Updated email address', example: 'user@example.com', nullable: true)]
    public ?string $email;

    #[OA\Property(description: 'Updated first name', example: 'John', nullable: true)]
    public ?string $first_name;

    #[OA\Property(description: 'Updated last name', example: 'Doe', nullable: true)]
    public ?string $last_name;

    #[OA\Property(description: 'New password (must be strong)', example: 'P@ssw0rd123!', nullable: true)]
    public ?string $password;

    #[OA\Property(description: 'URL to user avatar image', example: 'https://example.com/avatar.jpg', nullable: true)]
    public ?string $avatar_url;

    /** @var list<int>|null */
    #[OA\Property(
        description: 'Replace the user role set with this list of global role ids. Omit to leave roles unchanged.',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [3],
        nullable: true
    )]
    public ?array $role_ids;

    public function rules(): array
    {
        return [
            'email'      => 'permit_empty|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'password'   => 'permit_empty|strong_password',
            'avatar_url' => 'permit_empty|valid_url|max_length[255]',
            'role_ids'   => 'permit_empty|is_list',
        ];
    }

    protected function map(array $data): void
    {
        $this->email      = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name  = $data['last_name'] ?? null;
        $this->password   = $data['password'] ?? null;
        $this->avatar_url = $data['avatar_url'] ?? null;
        $this->role_ids   = array_key_exists('role_ids', $data) ? self::normalizeRoleIds($data['role_ids']) : null;
    }

    public function toArray(): array
    {
        $base = array_filter([
            'email'      => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'password'   => $this->password,
            'avatar_url' => $this->avatar_url,
        ], fn ($v) => $v !== null);

        if ($this->role_ids !== null) {
            $base['role_ids'] = $this->role_ids;
        }

        return $base;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private static function normalizeRoleIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $clean[] = (int) $value;
            }
        }
        return array_values(array_unique($clean));
    }
}
