<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListUserPermissionsRequest',
    title: 'List User Permissions Request',
    required: ['app']
)]
readonly class ListUserPermissionsRequestDTO extends BaseRequestDTO
{
    public string $app;

    public function rules(): array
    {
        return [
            'app' => 'required|alpha_dash|max_length[50]',
        ];
    }

    protected function map(array $data): void
    {
        $this->app = (string) ($data['app'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'app' => $this->app,
        ];
    }
}
