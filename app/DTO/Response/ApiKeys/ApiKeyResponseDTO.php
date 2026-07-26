<?php

declare(strict_types=1);

namespace App\DTO\Response\ApiKeys;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Api Key Response DTO
 */
#[OA\Schema(
    schema: 'ApiKeyResponse',
    title: 'API Key Response',
    description: 'API key object metadata',
    required: ['id', 'name', 'key_prefix', 'is_active']
)]
readonly class ApiKeyResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique API key identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'Human-readable label for the API key', example: 'My Mobile App')]
        public string $name,
        #[OA\Property(property: 'key_prefix', description: 'First characters of the key (safe to display)', example: 'apk_a3f9c2b1')]
        public string $key_prefix,
        #[OA\Property(property: 'is_active', description: 'Whether the key is currently active', example: true)]
        public bool $is_active,
        #[OA\Property(property: 'rate_limit_requests', description: 'Max requests per window', example: 600)]
        public int $rate_limit_requests,
        #[OA\Property(property: 'rate_limit_window', description: 'Window in seconds', example: 60)]
        public int $rate_limit_window,
        #[OA\Property(property: 'user_rate_limit', description: 'Per-user limit', example: 60)]
        public int $user_rate_limit,
        #[OA\Property(property: 'ip_rate_limit', description: 'Per-IP limit', example: 200)]
        public int $ip_rate_limit,
        #[OA\Property(description: 'Full raw API key (only returned once)', example: 'apk_a3f9...', nullable: true)]
        public ?string $key = null,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $created_at = null,
        #[OA\Property(property: 'updated_at', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updated_at = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? null;
        $updated_at = $data['updated_at'] ?? null;

        foreach ([$created_at, $updated_at] as &$date) {
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d H:i:s');
            }
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            key_prefix: (string) ($data['key_prefix'] ?? ''),
            is_active: (bool) ($data['is_active'] ?? true),
            rate_limit_requests: (int) ($data['rate_limit_requests'] ?? 600),
            rate_limit_window: (int) ($data['rate_limit_window'] ?? 60),
            user_rate_limit: (int) ($data['user_rate_limit'] ?? 60),
            ip_rate_limit: (int) ($data['ip_rate_limit'] ?? 200),
            key: $data['key'] ?? null,
            created_at: $created_at ? (string) $created_at : null,
            updated_at: $updated_at ? (string) $updated_at : null
        );
    }

    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'is_active' => $this->is_active,
            'rate_limit_requests' => $this->rate_limit_requests,
            'rate_limit_window' => $this->rate_limit_window,
            'user_rate_limit' => $this->user_rate_limit,
            'ip_rate_limit' => $this->ip_rate_limit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->key !== null) {
            $result['key'] = $this->key;
        }

        return $result;
    }
}
