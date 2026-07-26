<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

readonly class ApplicationSummary
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {
    }

    /**
     * @return array{id: int, code: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'id'   => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
