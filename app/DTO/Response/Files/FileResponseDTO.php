<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * File Response DTO
 *
 * Standardized output for file metadata.
 */
#[OA\Schema(
    schema: 'FileResponse',
    title: 'File Response',
    description: 'File metadata and access URL',
    required: ['id', 'original_name', 'mime_type', 'size', 'url']
)]
readonly class FileResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed>|null $variants
     */
    public function __construct(
        #[OA\Property(description: 'Unique file identifier', example: 1)]
        public int $id,
        #[OA\Property(property: 'original_name', description: 'Original filename', example: 'document.pdf')]
        public string $original_name,
        #[OA\Property(description: 'Opaque stored filename/key', example: 'f8c0d1e2f3a4-9b8c7d6e.pdf')]
        public string $filename,
        #[OA\Property(property: 'mime_type', description: 'MIME type', example: 'application/pdf')]
        public string $mime_type,
        #[OA\Property(property: 'category', description: 'File category derived from MIME type', example: 'document')]
        public string $category,
        #[OA\Property(property: 'size', description: 'File size in bytes', example: 102400)]
        public int $file_size,
        #[OA\Property(property: 'human_size', description: 'Human readable file size', example: '100 KB')]
        public string $human_size,
        #[OA\Property(property: 'is_image', description: 'Whether the file is an image', example: false)]
        public bool $is_image,
        #[OA\Property(description: 'Public or temporary access URL', example: 'https://example.com/storage/file.pdf')]
        public string $url,
        #[OA\Property(property: 'uploaded_at', description: 'Upload timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $created_at = null,
        #[OA\Property(property: 'variants', description: 'Generated image variants (thumb, sm, md)', nullable: true)]
        public ?array $variants = null,
        #[OA\Property(property: 'width', description: 'Original image width in pixels', nullable: true)]
        public ?int $width = null,
        #[OA\Property(property: 'height', description: 'Original image height in pixels', nullable: true)]
        public ?int $height = null,
        #[OA\Property(property: 'alt_text', description: 'Accessibility alt text for images', nullable: true)]
        public ?string $alt_text = null,
        #[OA\Property(property: 'caption', description: 'Display caption', nullable: true)]
        public ?string $caption = null,
        #[OA\Property(property: 'credit', description: 'Attribution credit', nullable: true)]
        public ?string $credit = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? $data['uploaded_at'] ?? null;
        if ($created_at instanceof \DateTimeInterface) {
            $created_at = $created_at->format('Y-m-d H:i:s');
        }

        // Handle case where we receive an entity array or raw data
        $size = (int) ($data['file_size'] ?? $data['size'] ?? 0);
        $mime = (string) ($data['mime_type'] ?? '');
        $derivedCategory = self::categoryFromMime($mime);
        $category = (string) ($data['category'] ?? '');
        if ($derivedCategory !== 'document') {
            $category = $derivedCategory;
        } elseif ($category === '') {
            $category = $derivedCategory;
        }
        $is_image = str_starts_with($mime, 'image/');

        $variants = $data['variants'] ?? null;
        if (is_string($variants)) {
            $variants = json_decode($variants, true);
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            original_name: (string) ($data['original_name'] ?? ''),
            filename: (string) ($data['filename'] ?? $data['stored_name'] ?? ''),
            mime_type: $mime,
            category: $category,
            file_size: $size,
            human_size: (string) ($data['human_size'] ?? self::calculateHumanSize($size)),
            is_image: (bool) ($data['is_image'] ?? $is_image),
            url: (string) ($data['url'] ?? ''),
            created_at: $created_at ? (string) $created_at : null,
            variants: is_array($variants) ? $variants : null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            alt_text: isset($data['alt_text']) ? (string) $data['alt_text'] : null,
            caption: isset($data['caption']) ? (string) $data['caption'] : null,
            credit: isset($data['credit']) ? (string) $data['credit'] : null,
        );
    }

    private static function calculateHumanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'original_name' => $this->original_name,
            'filename'      => $this->filename,
            'mime_type'     => $this->mime_type,
            'category'      => $this->category,
            'size'          => $this->file_size,
            'human_size'    => $this->human_size,
            'is_image'      => $this->is_image,
            'url'           => $this->url,
            'uploaded_at'   => $this->created_at,
            'variants'      => $this->variants,
            'width'         => $this->width,
            'height'        => $this->height,
            'alt_text'      => $this->alt_text,
            'caption'       => $this->caption,
            'credit'        => $this->credit,
        ];
    }

    private static function categoryFromMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'application/') || str_starts_with($mime, 'text/')) {
            return 'document';
        }

        return 'other';
    }
}
