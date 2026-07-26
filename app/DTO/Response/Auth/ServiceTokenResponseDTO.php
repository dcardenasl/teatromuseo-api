<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Service Token Response DTO
 *
 * OAuth client_credentials-style response: a short-lived JWT issued for
 * a domain application (no user). The token's `sub` is `service:<app_code>`
 * and its `scope` carries the application's permission codes.
 */
#[OA\Schema(
    schema: 'ServiceTokenResponse',
    title: 'Service Token Response',
    description: 'JWT issued to a domain application via X-App-Key',
    required: ['access_token', 'token_type', 'expires_in', 'scope']
)]
readonly class ServiceTokenResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param list<string> $scope
     */
    public function __construct(
        #[OA\Property(property: 'access_token', description: 'Signed JWT (HS256) carrying the application scope', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...')]
        public string $access_token,
        #[OA\Property(property: 'token_type', description: 'Always "Bearer"', example: 'Bearer')]
        public string $token_type,
        #[OA\Property(property: 'expires_in', description: 'Token lifetime in seconds', example: 900)]
        public int $expires_in,
        #[OA\Property(
            property: 'scope',
            description: 'Permission codes embedded in the token',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'mydomain.access')
        )]
        public array $scope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            access_token: (string) ($data['access_token'] ?? ''),
            token_type: (string) ($data['token_type'] ?? 'Bearer'),
            expires_in: (int) ($data['expires_in'] ?? 0),
            scope: array_values(array_map('strval', (array) ($data['scope'] ?? []))),
        );
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->access_token,
            'token_type'   => $this->token_type,
            'expires_in'   => $this->expires_in,
            'scope'        => $this->scope,
        ];
    }
}
