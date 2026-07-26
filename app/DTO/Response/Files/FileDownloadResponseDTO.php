<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * File Download Response DTO
 *
 * Encapsulates file metadata required for direct download or URL redirect.
 */
#[OA\Schema(
    schema: 'FileDownloadResponse',
    title: 'File Download Response',
    description: 'File metadata for downloads and external storage',
    required: ['id', 'original_name', 'url', 'path', 'storage_driver']
)]
readonly class FileDownloadResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique file identifier', example: 1)]
        public int $id,
        #[OA\Property(property: 'original_name', description: 'Original filename', example: 'document.pdf')]
        public string $original_name,
        #[OA\Property(description: 'Public or temporary access URL', example: 'https://example.com/storage/document.pdf')]
        public string $url,
        #[OA\Property(description: 'Storage path', example: 'uploads/abc123_document.pdf')]
        public string $path,
        #[OA\Property(property: 'storage_driver', description: 'Storage driver name', example: 's3')]
        public string $storage_driver
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            original_name: (string) ($data['original_name'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            storage_driver: (string) ($data['storage_driver'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'url' => $this->url,
            'path' => $this->path,
            'storage_driver' => $this->storage_driver,
        ];
    }
}
