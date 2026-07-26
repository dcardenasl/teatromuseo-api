<?php

declare(strict_types=1);

namespace App\DTO\Response\Audit;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Audit Response DTO
 *
 * Detailed view of an audit log entry.
 */
#[OA\Schema(
    schema: 'AuditResponse',
    title: 'Audit Response',
    description: 'Audit log entry metadata',
    required: ['id', 'action', 'entity_type', 'created_at']
)]
readonly class AuditResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed> $old_values
     * @param array<string, mixed> $new_values
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[OA\Property(description: 'Unique log identifier', example: 10)]
        public int $id,
        #[OA\Property(description: 'Action performed', example: 'update', enum: ['create', 'update', 'delete', 'login_success', 'login_failure'])]
        public string $action,
        #[OA\Property(property: 'entity_type', description: 'Affected entity type', example: 'users')]
        public string $entity_type,
        #[OA\Property(property: 'entity_id', description: 'Affected entity ID', example: 5, nullable: true)]
        public ?int $entity_id,
        #[OA\Property(property: 'old_values', description: 'Values before the change', type: 'object', nullable: true)]
        public array $old_values,
        #[OA\Property(property: 'new_values', description: 'Values after the change', type: 'object', nullable: true)]
        public array $new_values,
        #[OA\Property(property: 'user_id', description: 'ID of user who performed the action', example: 1, nullable: true)]
        public ?int $user_id,
        #[OA\Property(property: 'user_email', description: 'Email of user who performed the action', example: 'admin@example.com', nullable: true)]
        public ?string $user_email,
        #[OA\Property(property: 'user_full_name', description: 'Full name of user who performed the action', example: 'Jane Doe', nullable: true)]
        public ?string $user_full_name,
        #[OA\Property(property: 'ip_address', description: 'IP address of the requester', example: '127.0.0.1', nullable: true)]
        public ?string $ip_address,
        #[OA\Property(property: 'user_agent', description: 'User-Agent of the requester', example: 'Mozilla/5.0...', nullable: true)]
        public ?string $user_agent,
        #[OA\Property(property: 'result', description: 'Execution result', example: 'success', enum: ['success', 'failure', 'denied'])]
        public string $result,
        #[OA\Property(property: 'severity', description: 'Event severity', example: 'info', enum: ['info', 'warning', 'critical'])]
        public string $severity,
        #[OA\Property(property: 'request_id', description: 'Request correlation ID', example: 'req_01HZY...', nullable: true)]
        public ?string $request_id,
        #[OA\Property(property: 'metadata', description: 'Additional sanitized metadata', type: 'object', nullable: true)]
        public array $metadata,
        #[OA\Property(property: 'created_at', description: 'Log timestamp', example: '2026-02-26 12:00:00')]
        public string $created_at
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? null;
        if ($created_at instanceof \DateTimeInterface) {
            $created_at = $created_at->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            action: (string) ($data['action'] ?? ''),
            entity_type: (string) ($data['entity_type'] ?? ''),
            entity_id: isset($data['entity_id']) ? (int) $data['entity_id'] : null,
            old_values: is_string($data['old_values'] ?? null) ? json_decode($data['old_values'], true) : ($data['old_values'] ?? []),
            new_values: is_string($data['new_values'] ?? null) ? json_decode($data['new_values'], true) : ($data['new_values'] ?? []),
            user_id: isset($data['user_id']) ? (int) $data['user_id'] : null,
            user_email: $data['user_email'] ?? null,
            user_full_name: $data['user_full_name'] ?? null,
            ip_address: $data['ip_address'] ?? null,
            user_agent: $data['user_agent'] ?? null,
            result: (string) ($data['result'] ?? 'success'),
            severity: (string) ($data['severity'] ?? 'info'),
            request_id: isset($data['request_id']) ? (string) $data['request_id'] : null,
            metadata: is_string($data['metadata'] ?? null) ? json_decode($data['metadata'], true) : ($data['metadata'] ?? []),
            created_at: (string) $created_at
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'user_id' => $this->user_id,
            'user_email' => $this->user_email,
            'user_full_name' => $this->user_full_name,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'result' => $this->result,
            'severity' => $this->severity,
            'request_id' => $this->request_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
