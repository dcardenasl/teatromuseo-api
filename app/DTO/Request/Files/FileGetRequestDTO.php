<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;

/**
 * File Get Request DTO
 *
 * Validates the request to fetch or delete a file, enforcing user ownership check.
 */
readonly class FileGetRequestDTO extends BaseRequestDTO
{
    public int $id;
    public int $user_id;

    public function rules(): array
    {
        return [
            'id' => 'required|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->id = (int) $data['id'];
        $this->user_id = (int) $data['user_id'];
    }

    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'user_id' => $this->user_id,
        ];
    }
}
