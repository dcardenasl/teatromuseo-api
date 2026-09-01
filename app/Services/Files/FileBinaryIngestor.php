<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\DTO\Response\Files\FileResponseDTO;
use App\Entities\FileEntity;
use App\Interfaces\Files\BinaryIngestionInterface;
use App\Interfaces\Files\FileRepositoryInterface;
use App\Interfaces\Files\VirusScannerServiceInterface;
use App\Libraries\Files\Base64Processor;
use App\Libraries\Files\ImageVariantProcessor;
use App\Libraries\Files\MultipartProcessor;
use App\Libraries\Files\StorageKeyGenerator;
use App\Libraries\Storage\StorageManager;
use App\Support\Files\ProcessedFile;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;

/**
 * Deep Module for the complete binary-ingestion lifecycle.
 *
 * Both create and replace cross this Seam so scanning, storage metadata and
 * compensation remain local to one implementation.
 */
final class FileBinaryIngestor implements BinaryIngestionInterface
{
    public function __construct(
        private readonly FileRepositoryInterface $fileRepository,
        private readonly ResponseMapperInterface $responseMapper,
        private readonly StorageManager $storage,
        private readonly StorageKeyGenerator $storageKeyGenerator,
        private readonly MultipartProcessor $multipartProcessor,
        private readonly Base64Processor $base64Processor,
        private readonly ImageVariantProcessor $imageVariantProcessor,
        private readonly ?VirusScannerServiceInterface $virusScanner = null,
    ) {
    }

    public function create(FileUploadRequestDTO $request, int $userId, string $visibility): FileResponseDTO
    {
        $staged = $this->stage($this->process($request));

        try {
            $fileId = $this->fileRepository->insert($this->metadata($staged, $visibility, $userId));
            if ($fileId === false || $fileId === true) {
                throw new ValidationException(lang('Files.save_failed'), $this->fileRepository->errors());
            }

            $saved = $this->fileRepository->find($fileId);
            if ($saved === null) {
                $this->fileRepository->purge((int) $fileId);
                throw new \RuntimeException(sprintf('File row %d disappeared after insert.', (int) $fileId));
            }

            /** @var FileResponseDTO $response */
            $response = $this->responseMapper->map($saved);
            return $response;
        } catch (\Throwable $exception) {
            $this->discard($staged);
            throw $exception;
        }
    }

    public function replace(FileEntity $existing, FileUploadRequestDTO $request, string $visibility): FileResponseDTO
    {
        $protectedPaths = $this->pathsFor($existing);
        $staged = $this->stage($this->process($request), $protectedPaths);
        $updated = false;

        try {
            $updated = $this->fileRepository->update(
                (int) $existing->id,
                $this->metadata($staged, $visibility),
            );
            if (! $updated) {
                throw new ValidationException(lang('Files.save_failed'), $this->fileRepository->errors());
            }

            $saved = $this->fileRepository->find((int) $existing->id);
            if ($saved === null) {
                throw new \RuntimeException(sprintf('File row %d disappeared after replace.', (int) $existing->id));
            }

            /** @var FileResponseDTO $response */
            $response = $this->responseMapper->map($saved);
        } catch (\Throwable $exception) {
            if ($updated) {
                $this->restoreMetadata($existing);
            }
            $this->discard($staged);
            throw $exception;
        }

        $this->retire($protectedPaths, $this->stagedPaths($staged));
        return $response;
    }

    private function process(FileUploadRequestDTO $request): ProcessedFile
    {
        return $request->isBase64()
            ? $this->base64Processor->process($request->file, $request->toArray())
            : $this->multipartProcessor->process($request->file);
    }

    /**
     * @param list<string> $protectedPaths
     * @return array{file: ProcessedFile, path: string, stored_name: string, content_hash: string, variants: array<string, array<string, mixed>>, dimensions: array{width: int|null, height: int|null}, protected_paths: list<string>}
     */
    private function stage(ProcessedFile $file, array $protectedPaths = []): array
    {
        $this->scanForMalware($file);
        $contentHash = $this->hashStream($file->contents);
        $storedName = $this->storageKeyGenerator->generate($file->extension, $contentHash);
        $path = date('Y/m/d') . '/' . $storedName;

        if (! $this->storage->put($path, $file->contents)) {
            throw new \RuntimeException(lang('Files.storage_error'));
        }

        $variants = [];
        $dimensions = ['width' => null, 'height' => null];
        if (in_array($file->mimeType, ImageVariantProcessor::PROCESSABLE, true)) {
            $result = $this->imageVariantProcessor->generate($path, $file->extension, $this->storage);
            $variants = $result['variants'];
            $dimensions = $result['dimensions'];
        }

        return compact('file', 'path', 'storedName', 'contentHash', 'variants', 'dimensions') + [
            'stored_name' => $storedName,
            'content_hash' => $contentHash,
            'protected_paths' => $protectedPaths,
        ];
    }

    /**
     * @param array{file: ProcessedFile, path: string, stored_name: string, content_hash: string, variants: array<string, array<string, mixed>>, dimensions: array{width: int|null, height: int|null}} $staged
     * @return array<string, mixed>
     */
    private function metadata(array $staged, string $visibility, ?int $userId = null): array
    {
        $file = $staged['file'];
        $metadata = [
            'original_name' => $this->normalizeOriginalName($file->originalName),
            'stored_name' => $staged['stored_name'],
            'mime_type' => $file->mimeType,
            'category' => $this->categoryFromMimeType($file->mimeType),
            'size' => $file->size,
            'storage_driver' => $this->storage->getDriverName(),
            'path' => $staged['path'],
            // Persist only a host-independent URL. The absolute URL is
            // resolved from the active storage driver when responding.
            'url' => $this->storage->relativeUrl($staged['path']),
            'metadata' => json_encode(array_filter([
                'extension' => $file->extension,
                'content_hash' => $staged['content_hash'],
                'uploaded_by' => $userId,
                'visibility' => $visibility,
            ], static fn (mixed $value): bool => $value !== null)),
            'variants' => $staged['variants'] !== [] ? json_encode($staged['variants']) : null,
            'width' => $staged['dimensions']['width'],
            'height' => $staged['dimensions']['height'],
        ];

        if ($userId !== null) {
            $metadata['user_id'] = $userId;
            $metadata['uploaded_at'] = date('Y-m-d H:i:s');
        }

        return $metadata;
    }

    /**
     * @param array{path: string, variants: array<string, array<string, mixed>>, protected_paths: list<string>} $staged
     */
    private function discard(array $staged): void
    {
        $protected = array_flip($staged['protected_paths']);
        foreach (array_reverse($this->stagedPaths($staged)) as $path) {
            if (! isset($protected[$path])) {
                $this->storage->delete($path);
            }
        }
    }

    /**
     * @param list<string> $oldPaths
     * @param list<string> $newPaths
     */
    private function retire(array $oldPaths, array $newPaths): void
    {
        $keep = array_flip($newPaths);
        foreach ($oldPaths as $path) {
            if (! isset($keep[$path]) && ! $this->storage->delete($path)) {
                log_message('warning', "Could not retire replaced file object: {$path}");
            }
        }
    }

    /**
     * @param array{path: string, variants: array<string, array<string, mixed>>} $staged
     * @return list<string>
     */
    private function stagedPaths(array $staged): array
    {
        $paths = [$staged['path']];
        foreach ($staged['variants'] as $variant) {
            if (isset($variant['path']) && is_string($variant['path'])) {
                $paths[] = $variant['path'];
            }
        }
        return array_values(array_unique($paths));
    }

    /** @return list<string> */
    private function pathsFor(FileEntity $file): array
    {
        $paths = [(string) $file->path];
        $variants = is_array($file->variants)
            ? $file->variants
            : (json_decode((string) ($file->variants ?? ''), true) ?: []);
        foreach ($variants as $variant) {
            if (is_array($variant) && isset($variant['path']) && is_string($variant['path'])) {
                $paths[] = $variant['path'];
            }
        }
        return array_values(array_filter(array_unique($paths)));
    }

    private function restoreMetadata(FileEntity $file): void
    {
        $fields = ['original_name', 'stored_name', 'mime_type', 'category', 'size', 'storage_driver', 'path', 'url', 'metadata', 'variants', 'width', 'height'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $file->{$field};
        }

        try {
            $this->fileRepository->update((int) $file->id, $data);
        } catch (\Throwable $restoreException) {
            log_message('critical', 'Could not restore file metadata after failed replace: ' . $restoreException->getMessage());
        }
    }

    private function scanForMalware(ProcessedFile $file): void
    {
        if ($this->virusScanner === null) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'api_upload_');
        if ($tempPath === false) {
            throw new \RuntimeException(lang('Files.temp_file_creation_failed'));
        }

        try {
            $tempStream = fopen($tempPath, 'wb');
            if ($tempStream === false) {
                throw new \RuntimeException(lang('Files.temp_file_creation_failed'));
            }
            rewind($file->contents);
            stream_copy_to_stream($file->contents, $tempStream);
            fclose($tempStream);

            if (! $this->virusScanner->isSafe($tempPath)) {
                throw new BadRequestException(lang('Files.malware_detected'));
            }
        } finally {
            @unlink($tempPath);
            rewind($file->contents);
        }
    }

    private function hashStream(mixed $stream): string
    {
        if (! is_resource($stream)) {
            throw new \RuntimeException(lang('Files.hash_stream_invalid'));
        }
        $context = hash_init('sha256');
        if (! hash_update_stream($context, $stream)) {
            throw new \RuntimeException(lang('Files.hash_stream_failed'));
        }
        rewind($stream);
        return hash_final($context);
    }

    private function normalizeOriginalName(string $originalName): string
    {
        $name = pathinfo(trim($originalName), PATHINFO_BASENAME);
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');
        if ($name === '') {
            return 'file';
        }
        return function_exists('mb_substr') ? mb_substr($name, 0, 255) : substr($name, 0, 255);
    }

    private function categoryFromMimeType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
