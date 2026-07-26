<?php

declare(strict_types=1);

namespace App\Libraries\Iam;

/**
 * Result of a self-permission sync operation.
 */
final readonly class SelfPermissionResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $created,
        public int $existing,
        public int $rejected,
        public array $errors,
    ) {
    }

    /**
     * @return array{created: int, existing: int, rejected: int, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'created'  => $this->created,
            'existing' => $this->existing,
            'rejected' => $this->rejected,
            'errors'   => $this->errors,
        ];
    }
}
