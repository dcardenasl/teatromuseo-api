<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RoleWorkspaceResponse',
    title: 'Role workspace response',
    properties: [
        new OA\Property(property: 'role', type: 'object', additionalProperties: true),
        new OA\Property(property: 'allPermissions', type: 'array', items: new OA\Items(ref: '#/components/schemas/PermissionResponse')),
        new OA\Property(property: 'assignedPermissionIds', type: 'array', items: new OA\Items(type: 'integer')),
    ]
)]
readonly class RoleWorkspaceResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed> $role
     * @param list<array<string, mixed>> $allPermissions
     * @param list<int> $assignedPermissionIds
     */
    public function __construct(
        public array $role,
        public array $allPermissions,
        public array $assignedPermissionIds,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $allPermissions = is_array($data['allPermissions'] ?? null) ? $data['allPermissions'] : [];
        $assigned = is_array($data['assignedPermissionIds'] ?? null) ? $data['assignedPermissionIds'] : [];

        return new self(
            role: is_array($data['role'] ?? null) ? $data['role'] : [],
            allPermissions: array_values(array_filter($allPermissions, 'is_array')),
            assignedPermissionIds: array_values(array_map('intval', $assigned)),
        );
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'allPermissions' => $this->allPermissions,
            'assignedPermissionIds' => $this->assignedPermissionIds,
        ];
    }
}
