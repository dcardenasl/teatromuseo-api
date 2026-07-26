<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Introspect Response DTO
 *
 * RFC 7662-style token introspection contract. Always returned with
 * HTTP 200 — the `valid` flag carries the verdict.
 */
#[OA\Schema(
    schema: 'IntrospectResponse',
    title: 'Introspect Response',
    description: 'Token introspection result',
    required: ['valid', 'permissions']
)]
readonly class IntrospectResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        #[OA\Property(property: 'valid', description: 'True if the token is valid, not expired and not revoked', example: true)]
        public bool $valid,
        #[OA\Property(property: 'uid', description: 'User ID from the token (null when invalid)', example: 1, nullable: true)]
        public ?int $uid,
        #[OA\Property(
            property: 'permissions',
            description: 'Effective permission codes carried by the token',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'users.read')
        )]
        public array $permissions,
        #[OA\Property(property: 'exp', description: 'Token expiration timestamp (Unix seconds)', example: 1735689600, nullable: true)]
        public ?int $exp,
        #[OA\Property(property: 'error', description: 'Reason when valid=false: invalid_or_expired | revoked', example: null, nullable: true)]
        public ?string $error,
        #[OA\Property(property: 'app_id', description: 'Application ID for which the permissions were resolved', example: 1, nullable: true)]
        public ?int $app_id = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valid: (bool) ($data['valid'] ?? false),
            uid: isset($data['uid']) ? (int) $data['uid'] : null,
            permissions: array_values(array_map('strval', (array) ($data['permissions'] ?? []))),
            exp: isset($data['exp']) ? (int) $data['exp'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            app_id: isset($data['app_id']) ? (int) $data['app_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'valid'       => $this->valid,
            'uid'         => $this->uid,
            'permissions' => $this->permissions,
            'exp'         => $this->exp,
            'error'       => $this->error,
            'app_id'      => $this->app_id,
        ];
    }
}
