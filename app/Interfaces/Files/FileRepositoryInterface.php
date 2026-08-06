<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use App\Entities\FileEntity;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;

/**
 * File Repository Interface
 *
 * @extends RepositoryInterface<FileEntity>
 */
interface FileRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a file by its stored key.
     */
    public function findByStoredName(string $storedName): ?object;

    /**
     * Count files by user
     */
    public function countByUser(int $userId): int;

    /**
     * Find a file by id including soft-deleted rows. Returns null only when
     * the id does not exist at all.
     */
    public function findIncludingTrashed(int $id): ?object;

    /**
     * Hard-delete a file row (bypassing soft-delete) by id.
     */
    public function purge(int $id): bool;

    /**
     * Find a file by its public URL, including trashed rows.
     * Returns null only when no matching URL exists at all.
     */
    public function findByUrl(string $url): ?object;

    /**
     * Batch-fetch raw public metadata columns for file IDs (internal M2M
     * endpoint). Returns the persisted `path`/`url`/`variants` columns
     * as-is — URL resolution (via StorageManager) is a service concern,
     * not a data-access one.
     *
     * @param array<int|string, mixed> $ids
     * @return array<int, array{id: int, path: string, url: string|null, variants: mixed}>
     */
    public function findPublicMetaBatch(array $ids): array;
}
