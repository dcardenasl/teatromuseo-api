<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Google Identity Response DTO
 *
 * Encapsulates verified identity data from Google.
 */
#[OA\Schema(
    schema: 'GoogleIdentityResponse',
    title: 'Google Identity Response',
    description: 'Verified identity data returned by Google',
    required: ['provider', 'provider_id', 'email']
)]
readonly class GoogleIdentityResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        #[OA\Property(description: 'Identity provider', example: 'google')]
        public string $provider,
        #[OA\Property(property: 'provider_id', description: 'Provider user identifier', example: '113337022221111122223')]
        public string $provider_id,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'first_name', description: 'User first name', example: 'Alex', nullable: true)]
        public ?string $first_name,
        #[OA\Property(property: 'last_name', description: 'User last name', example: 'Doe', nullable: true)]
        public ?string $last_name,
        #[OA\Property(property: 'avatar_url', description: 'Avatar URL', example: 'https://example.com/avatar.png', nullable: true)]
        public ?string $avatar_url,
        #[OA\Property(description: 'Raw identity claims', type: 'object', additionalProperties: true)]
        public array $claims
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: 'google',
            provider_id: (string) ($data['provider_id'] ?? $data['sub'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            first_name: $data['first_name'] ?? $data['given_name'] ?? null,
            last_name: $data['last_name'] ?? $data['family_name'] ?? null,
            avatar_url: $data['avatar_url'] ?? $data['picture'] ?? null,
            claims: $data['claims'] ?? $data
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_id' => $this->provider_id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'avatar_url' => $this->avatar_url,
            'claims' => $this->claims,
        ];
    }
}
