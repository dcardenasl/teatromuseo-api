<?php

declare(strict_types=1);

namespace App\DTO\Response\Admin;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

final readonly class DashboardSummaryResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed> $sections
     */
    public function __construct(
        public int $version,
        public string $generated_at,
        public array $sections,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sections = $data['sections'] ?? [];

        return new self(
            version: (int) ($data['version'] ?? 1),
            generated_at: (string) ($data['generated_at'] ?? date(DATE_ATOM)),
            sections: is_array($sections) ? $sections : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'generated_at' => $this->generated_at,
            'sections' => $this->sections,
        ];
    }
}
