<?php

declare(strict_types=1);

namespace App\DTO\Response\Metrics;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Metrics Payload Response DTO
 *
 * Wraps arbitrary metrics payloads while preserving original shape.
 */
#[OA\Schema(
    schema: 'MetricsPayloadResponse',
    title: 'Metrics Payload Response',
    description: 'Wrapper for raw metrics payloads'
)]
readonly class MetricsPayloadResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<int|string, mixed> $payload
     */
    public function __construct(
        #[OA\Property(
            description: 'Raw metrics payload (shape varies by endpoint)',
            type: 'object',
            additionalProperties: true
        )]
        public array $payload
    ) {
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(payload: $payload);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
