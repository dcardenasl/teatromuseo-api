<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Translations;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Fixture request DTO for HandlesTranslations integration tests.
 *
 * Carries `name` (the canonical-locale value) plus the polymorphic
 * `translations` map the trait expects:
 * `{ "name": "ES", "translations": { "en": { "name": "EN" } } }`.
 */
readonly class TranslationFixtureRequestDTO extends BaseRequestDTO
{
    public string $name;
    /** @var array<string, array<string, mixed>> */
    public array $translations;

    public function rules(): array
    {
        return [];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');

        $translations = $data['translations'] ?? [];
        $this->translations = is_array($translations) ? $translations : [];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
