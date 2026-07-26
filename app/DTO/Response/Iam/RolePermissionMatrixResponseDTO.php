<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RolePermissionMatrixResponse')]
final readonly class RolePermissionMatrixResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param list<array<string, mixed>> $applications
     * @param list<array<string, mixed>> $roles
     * @param array<int, list<int>> $assignments
     */
    public function __construct(
        public array $applications,
        public array $roles,
        public array $assignments,
    ) {
    }

    public function toArray(): array
    {
        return [
            'applications' => $this->applications,
            'roles'       => $this->roles,
            'assignments' => $this->assignments,
        ];
    }
}
