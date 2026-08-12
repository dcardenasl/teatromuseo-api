<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Request\Files\UpdateFileMetadataRequestDTO;
use App\DTO\Response\Files\FileDownloadResponseDTO;
use App\DTO\Response\Files\FilePickerManifestResponseDTO;
use App\DTO\Response\Files\FileResponseDTO;
use App\Interfaces\Files\BinaryIngestionInterface;
use App\Interfaces\Files\DomainFileUsageClientInterface;
use App\Interfaces\Files\FilePolicyServiceInterface;
use App\Interfaces\Files\FileReferenceRepositoryInterface;
use App\Interfaces\Files\FileRepositoryInterface;
use App\Interfaces\Files\FileServiceInterface;
use App\Libraries\Files\FilePickerManifestCache;
use App\Libraries\Files\ImageVariantProcessor;
use App\Libraries\Storage\StorageManager;
use App\Support\Files\FileAction;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Models\Traits\AppliesQueryOptions;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * File Service (Refactored)
 *
 * Orchestrates file operations using specialized processors and generators.
 */
class FileService implements FileServiceInterface
{
    use AppliesQueryOptions;
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    public function __construct(
        protected FileRepositoryInterface $fileRepository,
        protected \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface $responseMapper,
        protected StorageManager $storage,
        protected AuditServiceInterface $auditService,
        protected ImageVariantProcessor $imageVariantProcessor,
        protected FileReferenceRepositoryInterface $fileReferenceRepository,
        protected FilePolicyServiceInterface $filePolicy,
        protected BinaryIngestionInterface $binaryIngestion,
        protected DomainFileUsageClientInterface $domainFileUsageClient,
        protected FilePickerManifestCache $filePickerManifestCache,
    ) {
    }

    /**
     * Usages visible to the Hub (own file_references table) merged with
     * usages each domain app reports for this file. This is the full
     * picture — the Hub-only view (fileReferenceRepository::getByFileId())
     * is blind to catalog-domain/event-domain/cms-domain resources.
     *
     * @return array<array{source: string, resource: string, resource_id: int, label: string|null, role: string}>
     */
    private function collectAllUsages(int $fileId): array
    {
        return array_merge(
            $this->fileReferenceRepository->getByFileId($fileId),
            $this->domainFileUsageClient->collectUsages($fileId),
        );
    }

    /**
     * Upload a file
     */
    public function upload(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileUploadRequestDTO $request */
        if (! $this->filePolicy->canUpload($context)) {
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        $userId = $this->resolveUserId($context);
        $visibility = $this->filePolicy->resolveUploadVisibility($request, $context);

        $result = $this->binaryIngestion->create($request, $userId, $visibility);
        $this->filePickerManifestCache->invalidate();

        return $result;
    }

    /**
     * List user's files
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileIndexRequestDTO $request */
        if (! $this->filePolicy->canRead($context)) {
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        $userId = $this->resolveUserId($context);

        $trashedMode = $request->trashed;
        // `BaseRepository::paginateCriteria` wraps the same Model instance that
        // `$this->fileRepository->getModel()` returns. Toggling soft-delete
        // mode on the model here propagates to the wrapped QueryBuilder.
        $fileModel = $this->fileRepository instanceof \dcardenasl\Ci4ApiCore\Repositories\BaseRepository
            ? $this->fileRepository->getModel()
            : null;
        $baseCriteria = function (\dcardenasl\Ci4ApiCore\Filters\QueryBuilder $builder) use ($userId, $trashedMode, $fileModel, $context): void {
            if ($this->filePolicy->shouldScopeListingsToOwner($context)) {
                $builder->where('user_id', $userId);
            }
            if ($fileModel === null) {
                return;
            }
            if ($trashedMode === \App\DTO\Request\Files\FileIndexRequestDTO::TRASHED_ONLY) {
                $fileModel->onlyDeleted();
            } elseif ($trashedMode === \App\DTO\Request\Files\FileIndexRequestDTO::TRASHED_WITH) {
                $fileModel->withDeleted();
            }
        };

        $result = $this->fileRepository->paginateCriteria(
            $request->toArray(),
            $request->page,
            $request->per_page,
            $baseCriteria
        );

        $result['data'] = array_map(
            fn ($entity) => $this->responseMapper->map($entity),
            (array) $result['data']
        );

        return PaginatedResponseDTO::fromArray($result);
    }

    public function pickerManifest(?SecurityContext $context = null): FilePickerManifestResponseDTO
    {
        if ($context?->user_id === null || ! $this->filePolicy->canRead($context)) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $userId = (int) $context->user_id;
        $allFiles = ! $this->filePolicy->shouldScopeListingsToOwner($context);
        $manifest = $this->filePickerManifestCache->remember(
            $userId,
            $allFiles,
            fn (): array => $this->buildPickerManifest($userId, $allFiles),
        );

        return FilePickerManifestResponseDTO::fromArray($manifest);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, version: string}
     */
    private function buildPickerManifest(int $userId, bool $allFiles): array
    {
        $model = $this->fileRepository->getModel();
        $query = $model
            ->select('id, original_name, mime_type, category, path, url, variants, width, height, size')
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC');

        if (! $allFiles) {
            $query->where('user_id', $userId);
        }

        $entities = $query->findAll();
        $items = [];
        foreach ($entities as $entity) {
            $data = $entity instanceof \App\Entities\FileEntity ? $entity->toArray() : (array) $entity;
            $mime = (string) ($data['mime_type'] ?? '');
            $variants = $data['variants'] ?? [];
            if (is_string($variants)) {
                $variants = json_decode($variants, true) ?: [];
            }
            $thumbPath = is_array($variants) && is_array($variants['thumb'] ?? null)
                ? (string) ($variants['thumb']['path'] ?? '')
                : '';
            $originalPath = (string) ($data['path'] ?? '');
            $originalUrl = $originalPath !== '' ? $this->storage->url($originalPath) : '';
            $previewUrl = $thumbPath !== ''
                ? $this->storage->url($thumbPath)
                : ($mime === 'image/gif' ? $originalUrl : '');

            $items[] = [
                'id' => (int) ($data['id'] ?? 0),
                'original_name' => (string) ($data['original_name'] ?? ''),
                'mime_type' => $mime,
                'category' => (string) ($data['category'] ?? ''),
                'is_image' => str_starts_with($mime, 'image/'),
                'human_size' => $this->humanSize((int) ($data['size'] ?? 0)),
                'url' => $originalUrl,
                'preview_url' => $previewUrl,
                'width' => isset($data['width']) ? (int) $data['width'] : null,
                'height' => isset($data['height']) ? (int) $data['height'] : null,
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
            'version' => (string) $this->filePickerManifestCache->currentVersion(),
        ];
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $index = 0;
        while ($value > 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 2) . ' ' . $units[$index];
    }

    /**
     * Return JSON metadata for a single file without downloading the binary.
     */
    public function findById(int $id, ?SecurityContext $context = null): FileResponseDTO
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->user_id, FileAction::VIEW, $context);

        /** @var FileResponseDTO $response */
        $response = $this->responseMapper->map($file);
        return $response;
    }

    /**
     * Download a file
     */
    public function download(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $this->resolveUserId($context);
        $file = $this->findFileAndAuthorize($request->id, $userId, FileAction::DOWNLOAD, $context);

        $data = $file->toArray();
        $data['url'] = $this->storage->url((string) $file->path);

        return FileDownloadResponseDTO::fromArray($data);
    }

    /**
     * Soft-delete a file. Sets `deleted_at` + `deleted_by_user_id`. Storage
     * bytes are intentionally preserved so the file can be restored or
     * downloaded from the trash UI.
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->user_id, FileAction::DELETE, $context);

        if ($file->isTrashed()) {
            throw new BadRequestException(lang('Files.already_trashed'));
        }

        $usages = $this->collectAllUsages((int) $file->id);
        if ($usages !== []) {
            throw new ConflictException(lang('Files.in_use', [count($usages)]));
        }

        $result = $this->wrapInTransaction(function () use ($file, $context) {
            $this->fileRepository->update($file->id, ['deleted_by_user_id' => $context->user_id]);
            return $this->fileRepository->delete($file->id);
        });
        $this->domainFileUsageClient->broadcastInvalidate((int) $file->id);
        $this->filePickerManifestCache->invalidate();

        return $result;
    }

    /**
     * Restore a trashed file.
     */
    public function restore(int $id, ?SecurityContext $context = null): bool
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findTrashedFileAndAuthorize($id, $context->user_id, FileAction::RESTORE, $context);
        if (!$file->isTrashed()) {
            throw new BadRequestException(lang('Files.not_trashed'));
        }

        $result = $this->fileRepository->restore($file->id);
        $this->filePickerManifestCache->invalidate();

        return $result;
    }

    /**
     * Permanently delete a trashed file: removes the storage object then the
     * DB row. Refuses if the file is not currently trashed (force-delete is a
     * trash-only operation).
     */
    public function forceDestroy(int $id, ?SecurityContext $context = null): bool
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findTrashedFileAndAuthorize($id, $context->user_id, FileAction::FORCE_DELETE, $context);
        if (!$file->isTrashed()) {
            throw new BadRequestException(lang('Files.not_trashed'));
        }

        $usages = $this->collectAllUsages((int) $file->id);
        if ($usages !== []) {
            throw new ConflictException(lang('Files.in_use', [count($usages)]));
        }

        $result = $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            return $this->fileRepository->purge((int) $file->id);
        });
        $this->domainFileUsageClient->broadcastInvalidate((int) $file->id);
        $this->filePickerManifestCache->invalidate();

        return $result;
    }

    /**
     * Return a list of resources that reference a given file.
     *
     * @return array<array{resource: string, resource_id: int, label: string|null, role: string}>
     */
    public function getUsages(int $id, ?SecurityContext $context = null)
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize(
            $id,
            $context->user_id,
            FileAction::VIEW_USAGES,
            $context
        );

        return $this->collectAllUsages((int) $file->id);
    }

    /**
     * Delete existing variants, re-generate them from the stored original, and
     * persist the updated metadata. Only valid for processable image MIME types.
     *
     * @return array<string, array{path: string, url: string, width: int, height: int}>
     */
    public function regenerateVariants(int $id, ?SecurityContext $context = null)
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize(
            $id,
            $context->user_id,
            FileAction::REGENERATE_VARIANTS,
            $context
        );

        if (! in_array($file->mime_type, ImageVariantProcessor::PROCESSABLE, true)) {
            throw new BadRequestException(lang('Files.not_an_image'));
        }

        $existingVariants = is_array($file->variants)
            ? $file->variants
            : (json_decode((string) ($file->variants ?? ''), true) ?? []);

        $this->imageVariantProcessor->deleteVariants((array) $existingVariants, $this->storage);

        $extension   = strtolower(pathinfo((string) $file->stored_name, PATHINFO_EXTENSION));
        $variantResult = $this->imageVariantProcessor->generate((string) $file->path, $extension, $this->storage);

        $this->fileRepository->update((int) $file->id, [
            'variants' => $variantResult['variants'] !== [] ? json_encode($variantResult['variants']) : null,
            'width'    => $variantResult['dimensions']['width'],
            'height'   => $variantResult['dimensions']['height'],
        ]);
        $this->filePickerManifestCache->invalidate();

        return $variantResult['variants'];
    }

    /**
     * Replace a file's binary content. Stores the new file, then deletes the
     * old storage object. The DB record ID and all references are preserved.
     */
    public function replace(int $id, \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): FileResponseDTO
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        /** @var \App\DTO\Request\Files\FileUploadRequestDTO $request */
        $file = $this->findFileAndAuthorize($id, $context->user_id, FileAction::REPLACE, $context);

        if ($file->isTrashed()) {
            throw new BadRequestException(lang('Files.already_trashed'));
        }

        $visibility = $this->filePolicy->resolveUploadVisibility($request, $context);
        $result = $this->binaryIngestion->replace($file, $request, $visibility);
        $this->domainFileUsageClient->broadcastInvalidate((int) $file->id);
        $this->filePickerManifestCache->invalidate();

        return $result;
    }

    /**
     * Update editable metadata fields without touching the stored binary.
     */
    public function updateMetadata(int $id, UpdateFileMetadataRequestDTO $dto, ?SecurityContext $context = null): FileResponseDTO
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->user_id, FileAction::UPDATE_METADATA, $context);

        $this->fileRepository->update((int) $file->id, $dto->toArray());

        $updated = $this->fileRepository->find((int) $file->id);
        if ($updated === null) {
            throw new \RuntimeException(sprintf('File row %d disappeared after metadata update.', (int) $file->id));
        }

        /** @var FileResponseDTO $response */
        $response = $this->responseMapper->map($updated);
        $this->filePickerManifestCache->invalidate();

        return $response;
    }

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkDestroy($ids, ?SecurityContext $context = null)
    {
        return $this->runBulk($ids, fn (int $id) => $this->destroy($id, $context));
    }

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkRestore($ids, ?SecurityContext $context = null)
    {
        return $this->runBulk($ids, fn (int $id) => $this->restore($id, $context));
    }

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkForceDestroy($ids, ?SecurityContext $context = null)
    {
        return $this->runBulk($ids, fn (int $id) => $this->forceDestroy($id, $context));
    }

    /**
     * @param list<int>            $ids
     * @param callable(int): bool  $operation
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    protected function runBulk(array $ids, callable $operation): array
    {
        $results = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                $ok = (bool) $operation($id);
                $entry = ['id' => $id, 'ok' => $ok];
                if (!$ok) {
                    $entry['error'] = lang('Files.bulk_item_failed');
                }
                $results[] = $entry;
            } catch (\Throwable $e) {
                $results[] = [
                    'id'    => $id,
                    'ok'    => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    protected function resolveUserId(?SecurityContext $context): int
    {
        if ($context === null || $context->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        return (int) $context->user_id;
    }


    protected function findFileAndAuthorize(
        int $id,
        int $userId,
        FileAction $action,
        ?SecurityContext $context = null
    ): \App\Entities\FileEntity {
        return $this->locateAndAuthorize($id, $userId, $action, $context, false);
    }

    /**
     * Same as findFileAndAuthorize but includes trashed rows. Use for
     * restore/force-delete paths.
     */
    protected function findTrashedFileAndAuthorize(
        int $id,
        int $userId,
        FileAction $action,
        ?SecurityContext $context = null
    ): \App\Entities\FileEntity {
        return $this->locateAndAuthorize($id, $userId, $action, $context, true);
    }

    protected function locateAndAuthorize(
        int $id,
        int $userId,
        FileAction $action,
        ?SecurityContext $context,
        bool $includeTrashed
    ): \App\Entities\FileEntity {
        /** @var \App\Entities\FileEntity|null $file */
        $file = $includeTrashed
            ? $this->fileRepository->findIncludingTrashed($id)
            : $this->fileRepository->find($id);
        if (!$file) {
            throw new NotFoundException(lang('Files.file_not_found'));
        }

        if (! $this->filePolicy->canAccessFile($file, $userId, $action, $context)) {
            $deniedAction = 'unauthorized_file_' . $action->auditSuffix();
            $this->auditService->log(
                $deniedAction,
                'files',
                $id,
                [],
                ['requested_by' => $userId, 'owner_id' => (int) $file->user_id],
                $context,
                'denied',
                'critical'
            );
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return $file;
    }

    /**
     * @param array<int|string, mixed> $ids
     * @return array<int, array{id: int, url: string|null, variants: array<string, mixed>}>
     * @phpstan-ignore dtoFirst.arrayReturn, dtoFirst.arrayParameter
     */
    public function resolvePublicMetaBatch(array $ids): array
    {
        $rows = $this->fileRepository->findPublicMetaBatch($ids);

        $result = [];
        foreach ($rows as $fileId => $row) {
            $variants = $row['variants'];
            if (is_string($variants)) {
                $decoded  = json_decode($variants, true);
                $variants = is_array($decoded) ? $decoded : [];
            } elseif (! is_array($variants)) {
                $variants = [];
            }

            $path = $row['path'];
            $url  = $path !== '' ? $this->storage->url($path) : $row['url'];

            foreach ($variants as $key => $variant) {
                if (is_array($variant) && isset($variant['path']) && is_string($variant['path'])) {
                    $variants[$key]['url'] = $this->storage->url($variant['path']);
                }
            }

            $result[$fileId] = [
                'id'       => $row['id'],
                'url'      => $url,
                'variants' => $variants,
            ];
        }

        return $result;
    }
}
