<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Register Response DTO
 *
 * Ensures the API contract is respected for registration responses.
 */
#[OA\Schema(
    schema: 'RegisterResponse',
    title: 'Register Response',
    description: 'User data returned after registration',
    required: ['id', 'email', 'first_name', 'last_name', 'status']
)]
readonly class RegisterResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'newuser@example.com')]
        public string $email,
        #[OA\Property(property: 'first_name', description: 'User first name', example: 'Alex')]
        public string $first_name,
        #[OA\Property(property: 'last_name', description: 'User last name', example: 'Doe')]
        public string $last_name,
        #[OA\Property(description: 'Account status', example: 'pending_approval', enum: ['pending_approval', 'active', 'invited'])]
        public string $status,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $created_at = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? null;

        // Normalize date to string if it's an object (CI4 Time or DateTime)
        if ($created_at instanceof \DateTimeInterface) {
            $created_at = $created_at->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            first_name: (string) ($data['first_name'] ?? ''),
            last_name: (string) ($data['last_name'] ?? ''),
            status: (string) ($data['status'] ?? 'pending_approval'),
            created_at: $created_at ? (string) $created_at : null
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
            'created_at' => $this->created_at,
        ];
    }
}
