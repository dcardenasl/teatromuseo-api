<?php

declare(strict_types=1);

namespace App\DTO\Response\Users;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * User Response DTO
 *
 * Standardized output for user data.
 */
#[OA\Schema(
    schema: 'UserResponse',
    title: 'User Response',
    description: 'User data returned by the API',
    required: ['id', 'email', 'status']
)]
readonly class UserResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'first_name', description: 'User first name', example: 'John', nullable: true)]
        public string $first_name,
        #[OA\Property(property: 'last_name', description: 'User last name', example: 'Doe', nullable: true)]
        public string $last_name,
        #[OA\Property(description: 'Account status', example: 'active', enum: ['pending_approval', 'active', 'invited'])]
        public string $status,
        #[OA\Property(property: 'avatar_url', description: 'URL to user avatar', example: 'https://example.com/avatar.png', nullable: true)]
        public ?string $avatar_url = null,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $created_at = null,
        #[OA\Property(property: 'updated_at', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updated_at = null,
        /** @var list<array{id:int, code:string, name:string}> */
        #[OA\Property(
            description: 'Global roles assigned to this user.',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                ]
            )
        )]
        public array $roles = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? null;
        $updated_at = $data['updated_at'] ?? null;

        if ($created_at instanceof \DateTimeInterface) {
            $created_at = $created_at->format('Y-m-d H:i:s');
        }
        if ($updated_at instanceof \DateTimeInterface) {
            $updated_at = $updated_at->format('Y-m-d H:i:s');
        }

        $roles = is_array($data['roles'] ?? null) ? array_values($data['roles']) : self::resolveRoles((int) ($data['id'] ?? 0));

        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            first_name: (string) ($data['first_name'] ?? ''),
            last_name: (string) ($data['last_name'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            avatar_url: isset($data['avatar_url']) ? (string) $data['avatar_url'] : null,
            created_at: $created_at ? (string) $created_at : null,
            updated_at: $updated_at ? (string) $updated_at : null,
            roles: $roles,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->roles,
        ];
    }

    /**
     * @return list<array{id:int, code:string, name:string}>
     */
    private static function resolveRoles(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $result = \Config\Database::connect()
                ->table('user_roles ur')
                ->select('r.id, r.code, r.name')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('ur.user_id', $userId)
                ->orderBy('r.name', 'ASC')
                ->get();
            $rows   = $result === false ? [] : $result->getResultArray();
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_map(static fn (array $r) => [
            'id'   => (int) $r['id'],
            'code' => (string) $r['code'],
            'name' => (string) $r['name'],
        ], $rows));
    }
}
