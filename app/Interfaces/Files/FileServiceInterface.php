<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * File Service Interface
 *
 * Contract for file upload, download, and deletion.
 */
interface FileServiceInterface
{
    /**
     * Upload a file
     */
    public function upload(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * List user's files
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Return the lightweight read model used by the admin file picker.
     */
    public function pickerManifest(?SecurityContext $context = null): \App\DTO\Response\Files\FilePickerManifestResponseDTO;

    /**
     * Download a file
     */
    public function download(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Return JSON metadata for a single file without downloading the binary.
     */
    public function findById(int $id, ?SecurityContext $context = null): \App\DTO\Response\Files\FileResponseDTO;

    /**
     * Soft-delete a file: sets `deleted_at` and `deleted_by_user_id` but keeps
     * the bytes on disk so a later `restore()` is non-destructive.
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Restore a previously trashed file. Throws NotFoundException if the
     * file is not currently trashed.
     */
    public function restore(int $id, ?SecurityContext $context = null): bool;

    /**
     * Permanently delete a file: removes the storage object and the DB row.
     * Refuses if the file is not currently trashed (force-delete is only
     * reachable from the trash UI).
     */
    public function forceDestroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Return all resources that reference the given file.
     *
     * @return array<array{resource: string, resource_id: int, label: string|null, role: string}>
     */
    public function getUsages(int $id, ?SecurityContext $context = null);

    /**
     * Delete existing image variants and regenerate them from the stored original.
     *
     * @return array<string, array{path: string, url: string, width: int, height: int}>
     */
    public function regenerateVariants(int $id, ?SecurityContext $context = null);

    /**
     * Replace a file's binary content. Preserves the record ID, user ownership,
     * and all existing references. Deletes the old storage object.
     */
    public function replace(int $id, \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\DTO\Response\Files\FileResponseDTO;

    /**
     * Update editable metadata fields (original_name, alt_text, caption, credit, category).
     * At least one field must be provided.
     */
    public function updateMetadata(int $id, \App\DTO\Request\Files\UpdateFileMetadataRequestDTO $dto, ?SecurityContext $context = null): \App\DTO\Response\Files\FileResponseDTO;

    /**
     * Bulk variants. Return per-item outcomes so the admin can show a partial
     * success summary: each entry is `{id: int, ok: bool, error?: string}`.
     *
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkDestroy($ids, ?SecurityContext $context = null);

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkRestore($ids, ?SecurityContext $context = null);

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkForceDestroy($ids, ?SecurityContext $context = null);

    /**
     * Batch-resolve public metadata (id, url, variants) for a set of file
     * IDs, for internal M2M consumption by trusted Domain apps. Resolves
     * relative storage paths to absolute URLs via StorageManager.
     *
     * @param array<int|string, mixed> $ids
     * @return array<int, array{id: int, url: string|null, variants: array<string, mixed>}>
     */
    public function resolvePublicMetaBatch(array $ids): array;
}
