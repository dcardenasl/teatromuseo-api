<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\TranslationModel;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Adds multilingual content support to any BaseCrudService.
 *
 * Usage in a service:
 *
 *   use HandlesTranslations;
 *
 *   protected function getTranslatableType(): string { return 'shows'; }
 *
 * The trait intercepts store/update to persist translations inside the same
 * transaction opened by `BaseCrudService::wrapInTransaction()`, and overrides
 * `mapToResponse()` to inject them into every response DTO.
 *
 * Active locale codes are read from `Config\App::$supportedLocales`. To allow
 * locale management at runtime instead, override `getActiveLocaleCodes()` in
 * the consuming service.
 *
 * Request format:
 *   { "title": "ES title", "translations": { "en": { "title": "EN title" }, "fr": {...} } }
 *
 * Response format (all GET endpoints):
 *   { "id": 1, "title": "ES title", "translations": { "en": { "title": "EN title" } } }
 *
 * The Request DTO consuming this trait must expose a public `translations`
 * property (typically `array $translations = []`); the trait reads it before
 * the parent `store()`/`update()` runs `toArray()`.
 */
trait HandlesTranslations
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $pendingTranslations = null;

    /**
     * The table name used as `translatable_type` in the translations table.
     */
    abstract protected function getTranslatableType(): string;

    /**
     * Override store() to capture translations from the DTO before toArray() strips them.
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        $this->pendingTranslations = $this->extractTranslations($request);

        return parent::store($request, $context);
    }

    /**
     * Override update() to capture translations from the DTO before toArray() strips them.
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        $this->pendingTranslations = $this->extractTranslations($request);

        return parent::update($id, $request, $context);
    }

    protected function afterStore(object $entity, ?SecurityContext $context): void
    {
        parent::afterStore($entity, $context);
        $this->flushPendingTranslations((int) $entity->id);
    }

    protected function afterUpdate(object $entity, ?SecurityContext $context): void
    {
        parent::afterUpdate($entity, $context);
        $this->flushPendingTranslations((int) $entity->id);
    }

    /**
     * After delete hook — cascades to the polymorphic translations table.
     *
     * `BaseCrudService::destroy()` opens its own transaction and invokes this
     * hook AFTER `repository->delete()`, so the cascade is part of the same
     * atomic unit. Translations are removed unconditionally regardless of
     * whether the parent uses soft- or hard-delete: the join is by
     * (translatable_type, translatable_id) and a future row reusing that id
     * (unlikely with autoincrement, impossible with soft-delete) would
     * otherwise inherit stale strings.
     */
    protected function afterDelete(object $entity, ?SecurityContext $context): void
    {
        parent::afterDelete($entity, $context);
        model(TranslationModel::class)->deleteForEntity(
            $this->getTranslatableType(),
            (int) $entity->id
        );
    }

    /**
     * Override mapToResponse to inject translations into every response DTO.
     */
    protected function mapToResponse(object $entity): DataTransferObjectInterface
    {
        $translations = model(TranslationModel::class)->getForEntity(
            $this->getTranslatableType(),
            (int) $entity->id
        );

        $data                 = method_exists($entity, 'toArray') ? $entity->toArray() : (array) $entity;
        $data['translations'] = $translations !== [] ? $translations : null;

        return $this->responseMapper->map($data);
    }

    /**
     * Active locale codes accepted by this service. Defaults to the project's
     * `Config\App::$supportedLocales`. Override to source codes from elsewhere
     * (e.g. a `locales` table managed by an admin).
     *
     * @return list<string>
     */
    protected function getActiveLocaleCodes(): array
    {
        /** @var \Config\App $appConfig */
        $appConfig = config('App');

        return array_values($appConfig->supportedLocales);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function extractTranslations(DataTransferObjectInterface $request): ?array
    {
        if (! property_exists($request, 'translations')) {
            return null;
        }

        /** @var mixed $value */
        $value = $request->translations;

        return is_array($value) ? $value : null;
    }

    /**
     * Persist all pending translations and clear the buffer. Called from
     * afterStore/afterUpdate, which run inside the open transaction.
     */
    private function flushPendingTranslations(int $id): void
    {
        if ($this->pendingTranslations === null) {
            return;
        }

        $model        = model(TranslationModel::class);
        $type         = $this->getTranslatableType();
        $activeCodes  = $this->getActiveLocaleCodes();

        foreach ($this->pendingTranslations as $locale => $fields) {
            if (! is_array($fields) || ! in_array((string) $locale, $activeCodes, true)) {
                continue;
            }

            foreach ($fields as $field => $value) {
                if ($value !== null && $value !== '') {
                    $model->upsertTranslation($type, $id, (string) $locale, (string) $field, (string) $value);
                }
            }
        }

        $this->pendingTranslations = null;
    }
}
