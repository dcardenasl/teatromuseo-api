<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use App\DTO\Response\Users\UserResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Canonical shape for the authenticated subject ("me").
 *
 * Returned by every endpoint that surfaces the caller's own user data:
 * `POST /auth/login`, `POST /auth/refresh`, `GET /auth/me`,
 * `PATCH /auth/me`. Extends `UserResponseDTO` with the effective permission
 * codes the caller holds in the current application — these drive UI
 * gating on the consuming frontend (e.g. `has_permission('users.write')`).
 *
 * Permissions are resolved fresh on each call from
 * `user_roles → roles → role_permissions → permissions`, so a revocation
 * is reflected on the next request without forcing a re-login.
 */
#[OA\Schema(
    schema: 'MeResponse',
    title: 'Authenticated User Response',
    description: 'Canonical shape for the authenticated subject. Extends UserResponse with the effective permission codes used for UI gating.',
    required: ['id', 'email', 'status', 'permissions']
)]
readonly class MeResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param list<array{id:int, code:string, name:string}> $roles
     * @param list<string>                                  $permissions
     */
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'first_name', description: 'User first name', example: 'John')]
        public string $first_name,
        #[OA\Property(property: 'last_name', description: 'User last name', example: 'Doe')]
        public string $last_name,
        #[OA\Property(description: 'Account status', example: 'active', enum: ['pending_approval', 'active', 'invited'])]
        public string $status,
        #[OA\Property(property: 'avatar_url', description: 'URL to user avatar', nullable: true)]
        public ?string $avatar_url,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', nullable: true)]
        public ?string $created_at,
        #[OA\Property(property: 'updated_at', description: 'Last update timestamp', nullable: true)]
        public ?string $updated_at,
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
        public array $roles,
        #[OA\Property(
            description: 'Effective permission codes for the current application. Drives UI gating on the frontend.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['users.read', 'users.write', 'iam.admin-access']
        )]
        public array $permissions,
    ) {
    }

    /**
     * Compose from the canonical user shape plus a resolved permissions list.
     *
     * @param list<string> $permissions
     */
    public static function fromUserResponse(UserResponseDTO $user, array $permissions): self
    {
        return new self(
            id: $user->id,
            email: $user->email,
            first_name: $user->first_name,
            last_name: $user->last_name,
            status: $user->status,
            avatar_url: $user->avatar_url,
            created_at: $user->created_at,
            updated_at: $user->updated_at,
            roles: $user->roles,
            permissions: array_values($permissions),
        );
    }

    /**
     * Compose from a User entity / array plus a resolved permissions list.
     *
     * @param array<string, mixed> $userData
     * @param list<string>         $permissions
     */
    public static function fromUserData(array $userData, array $permissions): self
    {
        return self::fromUserResponse(
            UserResponseDTO::fromArray($userData),
            $permissions
        );
    }

    /**
     * @param array<string, mixed> $data Expected to carry a `permissions` key.
     */
    public static function fromArray(array $data): self
    {
        $permissions = $data['permissions'] ?? [];
        if (! is_array($permissions)) {
            $permissions = [];
        }

        return self::fromUserData(
            $data,
            array_values(array_map('strval', $permissions))
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'email'        => $this->email,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'status'       => $this->status,
            'avatar_url'   => $this->avatar_url,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'roles'        => $this->roles,
            'permissions'  => $this->permissions,
        ];
    }
}
