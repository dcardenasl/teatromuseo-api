<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AttachPermissionsRequest', required: ['permission_ids'])]
readonly class AttachPermissionsRequestDTO extends BaseRequestDTO
{
    /** @var int[] */
    #[OA\Property(description: 'Permission ids to attach to the role', type: 'array', items: new OA\Items(type: 'integer'))]
    public array $permission_ids;

    /** @var string[]|null */
    #[OA\Property(description: 'Permission codes to attach to the role', type: 'array', items: new OA\Items(type: 'string'))]
    public ?array $permission_codes;

    public function rules(): array
    {
        return [
            'permission_ids'     => 'permit_empty',
            'permission_ids.*'   => 'permit_empty|integer|is_natural_no_zero',
            'permission_codes'   => 'permit_empty',
            'permission_codes.*' => 'permit_empty|string',
        ];
    }

    protected function map(array $data): void
    {
        $ids = $data['permission_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }
        $this->permission_ids = array_values(array_unique(array_map(static fn ($v) => (int) $v, $ids)));

        $codes = $data['permission_codes'] ?? null;
        if (is_array($codes)) {
            $this->permission_codes = array_values(array_unique(array_map(static fn ($v) => (string) $v, $codes)));
        } else {
            $this->permission_codes = null;
        }
    }

    public function toArray(): array
    {
        return [
            'permission_ids'   => $this->permission_ids,
            'permission_codes' => $this->permission_codes,
        ];
    }
}
