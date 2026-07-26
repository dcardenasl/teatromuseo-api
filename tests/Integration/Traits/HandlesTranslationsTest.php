<?php

declare(strict_types=1);

namespace Tests\Integration\Traits;

use App\Models\TranslationModel;
use Config\Database;
use dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper;
use dcardenasl\Ci4ApiCore\Repositories\GenericRepository;
use Tests\Support\Fixtures\Translations\TranslationFixtureModel;
use Tests\Support\Fixtures\Translations\TranslationFixtureRequestDTO;
use Tests\Support\Fixtures\Translations\TranslationFixtureResponseDTO;
use Tests\Support\Fixtures\Translations\TranslationFixtureService;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class HandlesTranslationsTest extends IntegrationTestCase
{
    private TranslationFixtureService $service;
    private TranslationFixtureModel $model;
    private TranslationModel $translations;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge();
        $forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('translation_test_parents', true);

        $this->model        = new TranslationFixtureModel();
        $this->translations = new TranslationModel();
        $this->service      = new TranslationFixtureService(
            new GenericRepository($this->model),
            new DtoResponseMapper(TranslationFixtureResponseDTO::class),
        );

        // Per-test isolation: $migrateOnce keeps schema across methods, so
        // wipe accumulated rows between tests instead of relying on $refresh.
        Database::connect()->table('translations')->truncate();
    }

    protected function tearDown(): void
    {
        Database::forge()->dropTable('translation_test_parents', true);
        parent::tearDown();
    }

    public function testStorePersistsTranslationsWithinTransaction(): void
    {
        $request = new TranslationFixtureRequestDTO([
            'name'         => 'Hola',
            'translations' => [
                'en' => ['name' => 'Hello'],
                'es' => ['name' => 'Hola alt'],
            ],
        ]);

        $response = $this->service->store($request);

        $this->assertInstanceOf(TranslationFixtureResponseDTO::class, $response);
        $this->assertSame('Hola', $response->name);
        $this->assertSame(
            ['en' => ['name' => 'Hello'], 'es' => ['name' => 'Hola alt']],
            $response->translations,
        );

        $stored = $this->translations->getForEntity('translation_test_parents', $response->id);
        $this->assertSame(['en' => ['name' => 'Hello'], 'es' => ['name' => 'Hola alt']], $stored);
    }

    public function testUpdateUpsertsTranslations(): void
    {
        $created = $this->service->store(new TranslationFixtureRequestDTO([
            'name'         => 'Hola',
            'translations' => ['en' => ['name' => 'Hello']],
        ]));

        $this->service->update($created->id, new TranslationFixtureRequestDTO([
            'name'         => 'Hola',
            'translations' => [
                'en' => ['name' => 'Hello (updated)'],
                'es' => ['name' => 'Nuevo'],
            ],
        ]));

        $stored = $this->translations->getForEntity('translation_test_parents', $created->id);
        $this->assertSame(
            ['en' => ['name' => 'Hello (updated)'], 'es' => ['name' => 'Nuevo']],
            $stored,
        );
    }

    public function testDeleteCascadesTranslations(): void
    {
        $created = $this->service->store(new TranslationFixtureRequestDTO([
            'name'         => 'Será borrado',
            'translations' => [
                'en' => ['name' => 'To be deleted'],
                'fr' => ['name' => 'À supprimer'],
            ],
        ]));

        $this->assertNotEmpty(
            $this->translations->getForEntity('translation_test_parents', $created->id),
            'Translations must exist before delete to make this test meaningful.',
        );

        $this->service->destroy($created->id);

        $this->assertSame(
            [],
            $this->translations->getForEntity('translation_test_parents', $created->id),
            'Translations must cascade away when the parent is deleted.',
        );
    }

    public function testReadIncludesTranslationsForExistingEntity(): void
    {
        $created = $this->service->store(new TranslationFixtureRequestDTO([
            'name'         => 'Ficha',
            'translations' => ['en' => ['name' => 'Card']],
        ]));

        $fetched = $this->service->show($created->id);

        $this->assertInstanceOf(TranslationFixtureResponseDTO::class, $fetched);
        $this->assertSame('Card', $fetched->translations['en']['name'] ?? null);
    }

    public function testReadReturnsNullTranslationsWhenNoneExist(): void
    {
        $created = $this->service->store(new TranslationFixtureRequestDTO([
            'name'         => 'Sin traducciones',
            'translations' => [],
        ]));

        $fetched = $this->service->show($created->id);

        $this->assertNull($fetched->translations);
    }
}
