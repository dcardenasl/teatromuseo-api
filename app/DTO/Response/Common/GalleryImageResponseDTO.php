<?php

declare(strict_types=1);

namespace App\DTO\Response\Common;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Response payload for a single gallery item: the pivot record enriched with
 * the underlying file's metadata (original name, image flag, variants).
 */
#[OA\Schema(schema: 'GalleryImageResponse')]
readonly class GalleryImageResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed>|null $variants
     */
    public function __construct(
        #[OA\Property(type: 'integer', example: 42)]
        public int $id,
        #[OA\Property(type: 'integer', example: 7)]
        public int $parent_id,
        #[OA\Property(type: 'string', example: '5')]
        public string $file_id,
        #[OA\Property(type: 'integer', example: 0)]
        public int $sort_order,
        #[OA\Property(type: 'boolean', example: true)]
        public bool $is_active,
        #[OA\Property(type: 'string', nullable: true, example: 'photo.jpg')]
        public ?string $original_name = null,
        #[OA\Property(type: 'boolean', nullable: true, example: true)]
        public ?bool $is_image = null,
        #[OA\Property(type: 'object', nullable: true)]
        public ?array $variants = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $variants = $data['variants'] ?? null;
        if (is_string($variants) && $variants !== '') {
            $decoded  = json_decode($variants, true);
            $variants = is_array($decoded) ? $decoded : null;
        } elseif (! is_array($variants)) {
            $variants = null;
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            parent_id: (int) ($data['parent_id'] ?? 0),
            file_id: (string) ($data['file_id'] ?? ''),
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: (bool) ($data['is_active'] ?? true),
            original_name: isset($data['original_name']) ? (string) $data['original_name'] : null,
            is_image: isset($data['is_image']) ? (bool) $data['is_image'] : null,
            variants: $variants,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $arr = [
            'id'         => $this->id,
            'parent_id'  => $this->parent_id,
            'file_id'    => $this->file_id,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->original_name !== null) {
            $arr['original_name'] = $this->original_name;
        }

        if ($this->is_image !== null) {
            $arr['is_image'] = $this->is_image;
        }

        if ($this->variants !== null) {
            $arr['variants'] = $this->variants;
        }

        return $arr;
    }
}
