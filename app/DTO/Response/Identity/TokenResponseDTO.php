<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\DTO\Response\Auth\MeResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Token Response DTO
 *
 * Returned by `POST /auth/refresh`. Carries a fresh access/refresh
 * token pair plus the canonical authenticated user (`MeResponse`) so
 * consumers can re-hydrate UI gating without an extra `/auth/me` call.
 */
#[OA\Schema(
    schema: 'TokenResponse',
    title: 'Token Response',
    required: ['access_token', 'refresh_token', 'expires_in', 'user']
)]
readonly class TokenResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(property: 'access_token', description: 'New JWT access token')]
        public string $access_token,
        #[OA\Property(property: 'refresh_token', description: 'New opaque refresh token')]
        public string $refresh_token,
        #[OA\Property(property: 'expires_in', description: 'Token expiration in seconds', example: 3600)]
        public int $expires_in,
        #[OA\Property(description: 'Authenticated user data with effective permissions', ref: '#/components/schemas/MeResponse')]
        public MeResponseDTO $user,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $user = $data['user'] ?? null;

        if (! $user instanceof MeResponseDTO) {
            $user = MeResponseDTO::fromArray(is_array($user) ? $user : []);
        }

        return new self(
            access_token: (string) ($data['access_token'] ?? ''),
            refresh_token: (string) ($data['refresh_token'] ?? ''),
            expires_in: (int) ($data['expires_in'] ?? 3600),
            user: $user,
        );
    }

    public function toArray(): array
    {
        return [
            'access_token'  => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'expires_in'    => $this->expires_in,
            'user'          => $this->user->toArray(),
        ];
    }
}
