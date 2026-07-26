<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Translations;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

/**
 * Fixture response DTO for HandlesTranslations integration tests.
 */
readonly class TranslationFixtureResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, array<string, mixed>>|null $translations
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?array $translations = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $translations = $data['translations'] ?? null;
        if (! is_array($translations)) {
            $translations = null;
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            translations: $translations,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'translations' => $this->translations,
        ];
    }
}
