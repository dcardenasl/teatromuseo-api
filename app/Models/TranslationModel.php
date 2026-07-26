<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Polymorphic translations table.
 *
 * One row per (entity type, entity id, locale, field, value). Used by services
 * that opt into the `App\Traits\HandlesTranslations` trait.
 */
class TranslationModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    protected $table      = 'translations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field',
        'value',
    ];

    /**
     * Get all translations for a single entity, grouped by locale then field.
     *
     * @return array<string, array<string, string>>  e.g. ['en' => ['title' => '...'], 'fr' => [...]]
     */
    public function getForEntity(string $type, int $id): array
    {
        $rows = $this->where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->findAll();

        $result = [];
        foreach ($rows as $row) {
            /** @var array<string, string> $row */
            $result[(string) $row['locale']][(string) $row['field']] = (string) $row['value'];
        }

        return $result;
    }

    /**
     * Upsert a single translation entry.
     *
     * Looks up the existing row by the unique tuple (type, id, locale, field)
     * and updates it; otherwise inserts a new row. The unique key on the table
     * is the final safety net: under a real race, the second writer's insert
     * would fail and the caller would retry with the updated row visible.
     */
    public function upsertTranslation(string $type, int $id, string $locale, string $field, string $value): void
    {
        $where = [
            'translatable_type' => $type,
            'translatable_id'   => $id,
            'locale'            => $locale,
            'field'             => $field,
        ];

        $exists = $this->builder()->where($where)->countAllResults() > 0;

        if ($exists) {
            $this->builder()
                ->where($where)
                ->update([
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            return;
        }

        $this->insert(array_merge($where, ['value' => $value]));
    }

    /**
     * Delete all translations for an entity. Call this from a service's
     * `afterDelete` hook when the parent record is hard-deleted.
     */
    public function deleteForEntity(string $type, int $id): void
    {
        $this->where('translatable_type', $type)
            ->where('translatable_id', $id)
            ->delete();
    }
}
