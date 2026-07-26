<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Minimal user envelope returned when a Google sign-in creates (or
 * reactivates) an account that still requires admin approval.
 *
 * This is intentionally NOT a `MeResponseDTO`: a pending account has no
 * effective permissions and no active session, so leaking the broader
 * shape would invite consumers to treat the response as an authenticated
 * subject. Carries only the identifiers the frontend needs to render
 * "your account is awaiting approval".
 */
#[OA\Schema(
    schema: 'PendingRegistrationResponse',
    title: 'Pending Registration Response',
    description: 'Minimal user envelope for accounts awaiting admin approval (e.g. first-time Google sign-in).',
    required: ['id', 'email', 'status']
)]
readonly class PendingRegistrationResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(description: 'Account status', example: 'pending_approval')]
        public string $status,
    ) {
    }

    public static function fromUser(object $user): self
    {
        return new self(
            id: (int) ($user->id ?? 0),
            email: (string) ($user->email ?? ''),
            status: (string) ($user->status ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            status: (string) ($data['status'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'email'  => $this->email,
            'status' => $this->status,
        ];
    }
}
