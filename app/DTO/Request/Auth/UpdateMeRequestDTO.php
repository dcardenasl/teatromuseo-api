<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Self-update request DTO for the authenticated user.
 *
 * Allowlist: only first_name, last_name and avatar_url. Email, password and
 * role assignments are intentionally NOT modifiable via self-update —
 * those flow through dedicated endpoints (admin update, password reset, IAM).
 */
#[OA\Schema(
    schema: 'UpdateMeRequest',
    title: 'Update Me Request',
    description: 'Fields the authenticated user may modify on their own profile'
)]
readonly class UpdateMeRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Updated first name', example: 'John', nullable: true)]
    public ?string $first_name;

    #[OA\Property(description: 'Updated last name', example: 'Doe', nullable: true)]
    public ?string $last_name;

    #[OA\Property(description: 'URL to user avatar image', example: 'https://example.com/avatar.jpg', nullable: true)]
    public ?string $avatar_url;

    public function rules(): array
    {
        return [
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'avatar_url' => 'permit_empty|valid_url|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->first_name = isset($data['first_name']) ? trim((string) $data['first_name']) : null;
        $this->last_name  = isset($data['last_name']) ? trim((string) $data['last_name']) : null;
        $this->avatar_url = isset($data['avatar_url']) ? trim((string) $data['avatar_url']) : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'avatar_url' => $this->avatar_url,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
