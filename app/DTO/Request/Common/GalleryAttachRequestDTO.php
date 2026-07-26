<?php

declare(strict_types=1);

namespace App\DTO\Request\Common;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Body for `POST /<resource>/<id>/images`. Attaches an existing file (already
 * uploaded via the files endpoint) to a parent resource's gallery.
 */
#[OA\Schema(schema: 'GalleryAttachRequest')]
readonly class GalleryAttachRequestDTO extends BaseRequestDTO
{
    public string $file_id;
    public ?int $sort_order;
    public ?bool $is_active;

    public function rules(): array
    {
        return [
            'file_id'    => 'required|string|max_length[36]',
            'sort_order' => 'permit_empty|integer',
            'is_active'  => 'permit_empty|boolean_like',
        ];
    }

    protected function map(array $data): void
    {
        $this->file_id    = (string) ($data['file_id'] ?? '');
        $this->sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : null;
        $this->is_active  = isset($data['is_active']) ? (bool) $data['is_active'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'file_id'    => $this->file_id,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ], static fn ($v) => $v !== null);
    }
}
