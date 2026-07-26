<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\DTO\Request\Common\GalleryAttachRequestDTO;
use App\DTO\Request\Common\GalleryReorderRequestDTO;
use App\DTO\Response\Common\GalleryImageResponseDTO;
use App\Interfaces\Files\FileRepositoryInterface;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Repositories\PivotRepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

/**
 * Reusable gallery service for pivot tables between a parent resource (Show,
 * Course, Exhibition, …) and the shared `files` table.
 *
 * The service is fully decoupled from `\CodeIgniter\Model`: it talks to the
 * pivot via `PivotRepositoryInterface` (which knows the parent FK and how to
 * order rows) and to the underlying files via `FileRepositoryInterface`
 * (which exposes `findByIds` for batch enrichment). Combine with
 * `HasGalleryActions` on the parent controller to expose the standard
 * endpoints (`images`, `attachImage`, `detachImage`, `reorderImages`).
 */
class GalleryService
{
    use HandlesTransactions;

    public function __construct(
        private readonly PivotRepositoryInterface $pivot,
        private readonly FileRepositoryInterface $files,
    ) {
    }

    /**
     * @return list<GalleryImageResponseDTO>
     */
    public function listFor(int $parentId)
    {
        $rows = $this->pivot->findByParent($parentId);

        if ($rows === []) {
            return [];
        }

        $fileData = $this->fetchFileMetadata($rows);

        return array_map(fn ($row) => $this->toResponse($row, $parentId, $fileData), $rows);
    }

    public function attach(int $parentId, GalleryAttachRequestDTO $request): GalleryImageResponseDTO
    {
        return $this->wrapInTransaction(function () use ($parentId, $request) {
            $data = [
                $this->pivot->getParentKey() => $parentId,
                'file_id'                    => $request->file_id,
                'sort_order'                 => $request->sort_order ?? ($this->pivot->maxSortOrder($parentId) + 1),
                'is_active'                  => ($request->is_active ?? true) ? 1 : 0,
            ];

            $id = $this->pivot->insert($data);
            if ($id === false || $id === 0 || $id === '' || $id === true) {
                throw new ValidationException(lang('Api.validationFailed'), $this->pivot->errors());
            }

            $row = $this->pivot->find($id);
            if (! $row) {
                throw new NotFoundException(lang('Api.resourceNotFound'));
            }

            $fileData = $this->fetchFileMetadata([$row]);

            return $this->toResponse($row, $parentId, $fileData);
        });
    }

    public function detach(int $parentId, int $pivotId): bool
    {
        $row = $this->pivot->find($pivotId);
        if (! $row) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        if ($this->rowParentId($row) !== $parentId) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $this->pivot->delete($pivotId);
    }

    /**
     * Bulk reorder. Pivot ids whose parent does not match `$parentId` are
     * silently skipped so the caller cannot reorder another parent's items.
     *
     * @return list<GalleryImageResponseDTO>
     */
    public function reorder(int $parentId, GalleryReorderRequestDTO $request)
    {
        return $this->wrapInTransaction(function () use ($parentId, $request) {
            foreach ($request->items as $item) {
                $row = $this->pivot->find($item['id']);
                if (! $row) {
                    continue;
                }
                if ($this->rowParentId($row) !== $parentId) {
                    continue;
                }
                $this->pivot->update($item['id'], ['sort_order' => $item['sort_order']]);
            }

            return $this->listFor($parentId);
        });
    }

    private function rowParentId(mixed $row): int
    {
        $data = $this->rowToArray($row);
        $raw  = $data[$this->pivot->getParentKey()] ?? 0;

        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowToArray(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if (is_object($row) && method_exists($row, 'toRawArray')) {
            return $row->toRawArray();
        }

        return is_object($row) ? (array) $row : [];
    }

    /**
     * @param array<string, array<string, mixed>> $fileData  keyed by file id (string)
     */
    private function toResponse(mixed $row, int $parentId, array $fileData = []): GalleryImageResponseDTO
    {
        $data   = $this->rowToArray($row);
        $fileId = (string) ($data['file_id'] ?? '');
        $meta   = $fileData[$fileId] ?? [];
        $sort   = $data['sort_order'] ?? 0;

        return GalleryImageResponseDTO::fromArray([
            'id'            => is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            'parent_id'     => $parentId,
            'file_id'       => $fileId,
            'sort_order'    => is_numeric($sort) ? (int) $sort : 0,
            'is_active'     => (bool) ($data['is_active'] ?? true),
            'original_name' => $meta['original_name'] ?? null,
            'is_image'      => $meta['is_image'] ?? null,
            'variants'      => $meta['variants'] ?? null,
        ]);
    }

    /**
     * Batch-fetch file metadata for a set of pivot rows so list responses do
     * not trigger N+1 queries.
     *
     * @param  list<mixed>                         $rows  raw pivot rows (arrays or entities)
     * @return array<string, array<string, mixed>>        keyed by file id (string)
     */
    private function fetchFileMetadata(array $rows): array
    {
        $fileIds = [];
        foreach ($rows as $row) {
            $id = (string) ($this->rowToArray($row)['file_id'] ?? '');
            if ($id !== '') {
                $fileIds[] = $id;
            }
        }

        $fileIds = array_values(array_unique($fileIds));
        if ($fileIds === []) {
            return [];
        }

        $files = $this->files->findByIds($fileIds);

        $result = [];
        foreach ($files as $file) {
            $raw = $this->rowToArray($file);
            $id  = (string) ($raw['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $variants = $raw['variants'] ?? null;
            if (is_string($variants) && $variants !== '') {
                $decoded  = json_decode($variants, true);
                $variants = is_array($decoded) ? $decoded : null;
            } elseif (! is_array($variants)) {
                $variants = null;
            }

            $result[$id] = [
                'original_name' => (string) ($raw['original_name'] ?? ''),
                'is_image'      => ($raw['category'] ?? '') === 'image',
                'variants'      => $variants,
            ];
        }

        return $result;
    }
}
