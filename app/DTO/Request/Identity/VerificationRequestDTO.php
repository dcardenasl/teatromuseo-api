<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * Verification Request DTO
 *
 * Validates the verification token provided in the URL or body.
 */
readonly class VerificationRequestDTO extends BaseRequestDTO
{
    public string $token;

    public function rules(): array
    {
        return [
            'token' => 'required|string|min_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->token = (string) ($data['token'] ?? '');

        // Defensive guardrail: keep deterministic validation even if validator state is altered.
        if (strlen($this->token) < 10) {
            throw new ValidationException(lang('Api.validationFailed'), [
                'token' => lang('InputValidation.auth.verificationTokenMinLength'),
            ]);
        }
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
