<?php

declare(strict_types=1);

namespace App\DTO\Request\Common;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Body for `PUT /<resource>/<id>/images/reorder`.
 *
 * Format: `{ "items": [{ "id": 12, "sort_order": 0 }, { "id": 13, "sort_order": 1 }, ...] }`.
 * Each entry is a pivot row id paired with its new sort position. Entries
 * pointing at a different parent are silently ignored by the service.
 */
#[OA\Schema(schema: 'GalleryReorderRequest')]
readonly class GalleryReorderRequestDTO extends BaseRequestDTO
{
    /** @var list<array{id:int, sort_order:int}> */
    public array $items;

    public function rules(): array
    {
        return [
            'items' => 'required',
        ];
    }

    protected function map(array $data): void
    {
        $items = [];
        foreach ((array) ($data['items'] ?? []) as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }
            $items[] = [
                'id'         => (int) $item['id'],
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ];
        }
        $this->items = $items;
    }

    public function toArray(): array
    {
        return ['items' => $this->items];
    }
}
