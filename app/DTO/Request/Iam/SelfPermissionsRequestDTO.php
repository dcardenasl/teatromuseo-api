<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

readonly class SelfPermissionsRequestDTO extends BaseRequestDTO
{
    /**
     * @var array<int, array<string, string>>|null
     */
    public ?array $permissions;

    public function rules(): array
    {
        return [
            'permissions' => 'required',
        ];
    }

    protected function map(array $data): void
    {
        $this->permissions = isset($data['permissions']) && is_array($data['permissions'])
            ? array_values($data['permissions'])  // @phpstan-ignore-line
            : null;
    }

    public function toArray(): array
    {
        return [
            'permissions' => $this->permissions,
        ];
    }
}
