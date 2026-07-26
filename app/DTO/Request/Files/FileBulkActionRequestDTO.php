<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * Bulk action payload for files trash endpoints.
 *
 * Accepts an array of file ids and normalises it to a unique list of
 * positive integers. Used by `POST /files/bulk-{delete,restore,force-delete}`.
 */
readonly class FileBulkActionRequestDTO extends BaseRequestDTO
{
    /** @var list<int> */
    public array $ids;
    public int $user_id;

    public function rules(): array
    {
        return [
            'ids' => 'required',
        ];
    }

    protected function map(array $data): void
    {
        $rawIds = $data['ids'] ?? null;
        if (!is_array($rawIds) || $rawIds === []) {
            throw new ValidationException(lang('Files.bulk_ids_required'), ['ids' => lang('Files.bulk_ids_required')]);
        }

        $cleaned = [];
        foreach ($rawIds as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0) {
                $cleaned[$id] = true;
            }
        }

        if ($cleaned === []) {
            throw new ValidationException(lang('Files.bulk_ids_required'), ['ids' => lang('Files.bulk_ids_required')]);
        }

        $this->ids = array_values(array_map('intval', array_keys($cleaned)));
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
    }

    public function toArray(): array
    {
        return [
            'ids'     => $this->ids,
            'user_id' => $this->user_id,
        ];
    }
}
