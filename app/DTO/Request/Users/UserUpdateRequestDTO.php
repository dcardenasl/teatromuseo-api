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

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * email is NOT NULL and never clearable. first_name/last_name/
     * avatar_url are nullable columns — an explicit null preserves through
     * to toArray() and actually clears them (e.g. removing a user's
     * avatar) — the bug this fixes is array_filter() silently dropping
     * every null, which made that impossible. password is deliberately
     * EXCLUDED from toArray()/mappedFields entirely, same as before: it is
     * never persisted here at all — UpdateUserAction reads
     * $request->password directly and only ever writes a freshly-hashed
     * value when non-null. There is no "clear password to null" operation
     * on this endpoint, and there must never be one — that would either
     * leave the account without any usable credential or silently disable
     * password login, and any legitimate reset belongs in a dedicated
     * reset-password flow, not an implicit side effect of this DTO's
     * general null-clearing semantics.
     */
    protected function map(array $data): void
    {
        $this->email      = array_key_exists('email', $data) && $data['email'] !== null ? strtolower(trim((string) $data['email'])) : null;
        $this->first_name = array_key_exists('first_name', $data) && $data['first_name'] !== null && $data['first_name'] !== '' ? (string) $data['first_name'] : null;
        $this->last_name  = array_key_exists('last_name', $data) && $data['last_name'] !== null && $data['last_name'] !== '' ? (string) $data['last_name'] : null;
        $this->password   = $data['password'] ?? null;
        $this->avatar_url = array_key_exists('avatar_url', $data) && $data['avatar_url'] !== null && $data['avatar_url'] !== '' ? (string) $data['avatar_url'] : null;
        $this->role_ids   = array_key_exists('role_ids', $data) ? self::normalizeRoleIds($data['role_ids']) : null;

        $mappedFields = [];
        if ($this->email !== null) {
            $mappedFields['email'] = $this->email;
        }
        if (array_key_exists('first_name', $data)) {
            $mappedFields['first_name'] = $this->first_name;
        }
        if (array_key_exists('last_name', $data)) {
            $mappedFields['last_name'] = $this->last_name;
        }
        if (array_key_exists('avatar_url', $data)) {
            $mappedFields['avatar_url'] = $this->avatar_url;
        }

        $this->mappedFields = $mappedFields;
    }

    /**
     * Deliberately excludes password and role_ids — see the note on
     * map() above. Callers that need those read $this->password /
     * $this->role_ids directly (UpdateUserAction::buildUpdateData() does
     * exactly this).
     */
    public function toArray(): array
    {
        return $this->mappedFields;
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
