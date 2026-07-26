<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * User Store Request DTO
 *
 * Validates data for creating a new user.
 */
#[OA\Schema(
    schema: 'UserCreateRequest',
    title: 'User Create Request',
    description: 'Data needed to create a new user',
    required: ['email']
)]
readonly class UserCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Unique email address', example: 'user@example.com')]
    public string $email;

    #[OA\Property(description: 'User first name', example: 'John', nullable: true)]
    public ?string $first_name;

    #[OA\Property(description: 'User last name', example: 'Doe', nullable: true)]
    public ?string $last_name;

    #[OA\Property(description: 'OAuth provider name', enum: ['google', 'github'], nullable: true)]
    public ?string $oauth_provider;

    #[OA\Property(description: 'OAuth unique identifier from provider', nullable: true)]
    public ?string $oauth_provider_id;

    #[OA\Property(description: 'URL to user avatar image', example: 'https://example.com/avatar.jpg', nullable: true)]
    public ?string $avatar_url;

    #[OA\Property(description: 'Preferred locale for localized notifications', example: 'es', nullable: true)]
    public ?string $locale;

    /** @var list<int> */
    #[OA\Property(
        description: 'Global role ids to assign to the new user (multi-rol). If empty, the default "user" role is assigned.',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [3]
    )]
    public array $role_ids;

    public function rules(): array
    {
        return [
            'email'     => 'required|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'password'  => 'permit_empty|max_length[0]', // Ensure password is not provided
            'oauth_provider' => 'permit_empty|in_list[google,github]',
            'oauth_provider_id' => 'permit_empty|string|max_length[255]',
            'avatar_url' => 'permit_empty|valid_url|max_length[255]',
            'locale'    => 'permit_empty|string|max_length[10]',
            'role_ids'  => 'permit_empty|is_list',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = (string) $data['email'];
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->oauth_provider = $data['oauth_provider'] ?? null;
        $this->oauth_provider_id = $data['oauth_provider_id'] ?? null;
        $this->avatar_url = $data['avatar_url'] ?? null;
        $locale = isset($data['locale']) ? strtolower(trim((string) $data['locale'])) : '';
        $this->locale = $locale !== '' ? $locale : null;
        $this->role_ids = self::normalizeRoleIds($data['role_ids'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'email'     => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'oauth_provider' => $this->oauth_provider,
            'oauth_provider_id' => $this->oauth_provider_id,
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            'role_ids' => $this->role_ids,
        ];
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
