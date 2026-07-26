<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

interface FileReferenceRepositoryInterface
{
    /**
     * Insert or update (upsert) a reference row.
     * Uses the unique key (resource_type, resource_id, role) to detect conflicts.
     */
    public function register(int $fileId, string $resourceType, int $resourceId, string $role, ?string $label = null): bool;

    /**
     * Remove the reference row matching the given resource + role.
     */
    public function unregisterByResource(string $resourceType, int $resourceId, string $role): bool;

    /**
     * Return all references pointing at a given file.
     *
     * @return array<array{source: string, resource: string, resource_id: int, label: string|null, role: string}>
     */
    public function getByFileId(int $fileId): array;
}
