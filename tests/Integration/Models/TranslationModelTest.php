<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\TranslationModel;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class TranslationModelTest extends IntegrationTestCase
{
    public function testModelReportsCorrectTable(): void
    {
        $model = new TranslationModel();

        $this->assertSame('translations', $model->getTable());
    }

    public function testUpsertInsertsAndUpdatesIdempotently(): void
    {
        $model = new TranslationModel();

        $model->upsertTranslation('shows', 1, 'en', 'title', 'Hello');
        $model->upsertTranslation('shows', 1, 'en', 'title', 'World');

        $rows = $model->where('translatable_type', 'shows')
            ->where('translatable_id', 1)
            ->findAll();

        $this->assertCount(1, $rows, 'Upsert must not duplicate (type, id, locale, field).');
        $this->assertSame('World', $rows[0]['value']);
    }

    public function testGetForEntityGroupsByLocaleThenField(): void
    {
        $model = new TranslationModel();

        $model->upsertTranslation('shows', 7, 'en', 'title', 'Title EN');
        $model->upsertTranslation('shows', 7, 'en', 'subtitle', 'Sub EN');
        $model->upsertTranslation('shows', 7, 'fr', 'title', 'Titre FR');
        $model->upsertTranslation('shows', 8, 'en', 'title', 'Other EN');

        $result = $model->getForEntity('shows', 7);

        // assertEquals (not assertSame) because we are testing the grouping
        // shape, not field order — MySQL does not guarantee row order
        // without ORDER BY, so the inner-array key order is incidental.
        $this->assertEquals([
            'en' => ['title' => 'Title EN', 'subtitle' => 'Sub EN'],
            'fr' => ['title' => 'Titre FR'],
        ], $result);
    }

    public function testDeleteForEntityRemovesAllRowsForThatEntityOnly(): void
    {
        $model = new TranslationModel();

        $model->upsertTranslation('shows', 11, 'en', 'title', 'Keep deleted');
        $model->upsertTranslation('shows', 12, 'en', 'title', 'Sibling');

        $model->deleteForEntity('shows', 11);

        $this->assertSame([], $model->getForEntity('shows', 11));
        $this->assertSame(['en' => ['title' => 'Sibling']], $model->getForEntity('shows', 12));
    }
}
