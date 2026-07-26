<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Introspect Request DTO
 *
 * Carries the JWT to be introspected. Authentication of the caller is
 * handled by the appKeyRequired filter (X-App-Key header).
 */
#[OA\Schema(
    schema: 'IntrospectRequest',
    title: 'Introspect Request',
    description: 'JWT to validate via the introspection endpoint',
    required: ['token']
)]
readonly class IntrospectRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'JWT access token to introspect', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...')]
    public string $token;

    public function rules(): array
    {
        return [
            'token' => 'required|string|min_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->token = trim((string) $data['token']);
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
