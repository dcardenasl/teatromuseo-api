<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\LegacyMigration\LegacyApplyService;
use App\Libraries\LegacyMigration\LegacyDomainClientInterface;
use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class LegacyApplyServiceTest extends IntegrationTestCase
{
    public function testSliceASecondPassReusesAllTargetsAndReference(): void
    {
        $hash = hash('sha256', 'legacy-apply-fixture');
        $tables = [
            'sn_compania' => [
                ['id_compania' => '7', 'nombre_compania' => 'Compañía Fixture', 'resena_compania' => 'Resumen', 'display_comp' => '1'],
            ],
            'sn_obra' => [
                [
                    'id_obra' => '1',
                    'titulo_obra' => 'Obra Fixture',
                    'url' => 'obra-fixture',
                    'fecha_obra' => '2026-08-01',
                    'hora_obra' => '20:00',
                    'valor1_obra' => '$ 3.000',
                    'valor2_obra' => '$ 2.500',
                    'direccion_obra' => 'Teatromuseo',
                    'id_publico' => '1',
                    'id_compania' => '7',
                    'display' => '1',
                ],
            ],
            'sn_slider_cartelera' => [],
            'sn_youtube' => [
                ['id_youtube' => '20', 'url' => 'fixture-video', 'nombre' => 'Video Fixture', 'fecha' => '2026-08-02', 'display' => '1'],
                ['id_youtube' => '21', 'url' => 'fixture-video', 'nombre' => 'Video Fixture duplicado', 'fecha' => '2026-08-02', 'display' => '1'],
            ],
            'sn_publico' => [
                ['id_publico' => '1', 'nombre_publico' => 'Familiar'],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);

        $firstRun = $repository->createRun('legacy-apply-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/fixture.sql', $hash);
        $first = $service->apply('A', $tables, '/tmp/fixture.sql', $firstRun);
        $repository->finishRun($firstRun, LegacyMigrationCatalog::RUN_COMPLETED, $first);

        $secondRun = $repository->createRun('legacy-apply-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/fixture.sql', $hash);
        $second = $service->apply('A', $tables, '/tmp/fixture.sql', $secondRun);
        $repository->finishRun($secondRun, LegacyMigrationCatalog::RUN_COMPLETED, $second);

        $this->assertSame(3, $first['created']['cms_entries']);
        $this->assertSame(1, $first['created']['events']);
        $this->assertSame(1, $first['created']['occurrences']);
        $this->assertSame(1, $first['created']['references']);
        $this->assertSame(0, $second['created']['cms_entries']);
        $this->assertSame(3, $second['reused']['cms_entries']);
        $this->assertSame(1, $second['reused']['events']);
        $this->assertSame(1, $second['reused']['occurrences']);
        $this->assertSame(1, $second['reused']['references']);
        $this->assertSame(1, $client->postCount('/events/event-references'));
        $this->assertSame(102, (int) $repository->findMap('sn_youtube', '21', LegacyMigrationCatalog::TARGET_CMS, 'entry')['target_id']);
        $entries = $client->payloads('/cms/entries');
        $this->assertSame('Familiar', $entries[1]['wizard_extra']['audience']);
        $this->assertSame('$ 3.000', $entries[1]['wizard_extra']['price_regular']);
        $this->assertSame('2026-08-02', $entries[2]['wizard_extra']['recorded_at']);
    }

    public function testSliceBSecondPassReusesCourseTeacherAndSupplementalMapping(): void
    {
        $hash = hash('sha256', 'legacy-course-fixture');
        $tables = [
            'sn_escuela' => [
                [
                    'curso_id' => '25',
                    'curso_titulo' => 'Taller Fixture',
                    'curso_descripcion' => 'Descripción base',
                    'curso_categoria' => '3',
                    'curso_fecha_inicio' => '2026-09-01',
                    'curso_fecha_termino' => '2026-10-01',
                    'curso_hora_inicio' => '18:00',
                    'curso_hora_termino' => '20:00',
                ],
            ],
            'sn_cursos' => [
                ['id' => '25', 'title' => 'Taller Fixture Actualizado', 'description_text' => 'Descripción suplementaria'],
            ],
            'sn_escuela_img' => [],
            'sn_profesor' => [
                ['profesor_id' => '8', 'profesor_nombre' => 'Docente Fixture', 'profesor_nacionalidad' => 'Chile', 'profesor_curso' => '25'],
            ],
            'sn_categoria_escuela' => [
                ['id' => '3', 'titulo' => 'Formación'],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);

        $firstRun = $repository->createRun('legacy-course-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-fixture.sql', $hash);
        $first = $service->apply('B', $tables, '/tmp/course-fixture.sql', $firstRun);
        $repository->finishRun($firstRun, LegacyMigrationCatalog::RUN_COMPLETED, $first);

        $secondRun = $repository->createRun('legacy-course-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-fixture.sql', $hash);
        $second = $service->apply('B', $tables, '/tmp/course-fixture.sql', $secondRun);
        $repository->finishRun($secondRun, LegacyMigrationCatalog::RUN_COMPLETED, $second);

        $this->assertSame(2, $first['created']['cms_entries']);
        $this->assertSame(0, $first['created']['events']);
        $this->assertSame(0, $second['created']['cms_entries']);
        $this->assertSame(2, $second['reused']['cms_entries']);
        $this->assertSame(0, $client->postCount('/events/events'));
        $this->assertSame(
            LegacyMigrationCatalog::MAP_SUPPLEMENTAL,
            $repository->findMap('sn_cursos', '25', LegacyMigrationCatalog::TARGET_CMS, 'entry')['status']
        );
    }
}

/**
 * Deterministic domain seam used to verify the apply contract without making
 * a test depend on a running CMS or Event process.
 */
final class LegacyApplyRecordingClient implements LegacyDomainClientInterface
{
    /** @var array<string, int> */
    private array $posts = [];
    /** @var array<string, list<array<string, mixed>>> */
    private array $payloads = [];
    private int $nextCmsId = 100;
    private int $nextEventId = 200;
    private int $nextOccurrenceId = 300;
    private int $nextReferenceId = 400;

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): array
    {
        if (in_array($path, ['/cms/block-types', '/events/events', '/events/occurrences'], true)) {
            if ((int) ($query['per_page'] ?? 0) > 100) {
                throw new \RuntimeException('Migration exceeded a public domain pagination contract.');
            }
        }

        return match ($path) {
            '/cms/collections' => ['data' => ['items' => [
                ['id' => 1, 'collection_key' => 'companias'],
                ['id' => 2, 'collection_key' => 'obras'],
                ['id' => 3, 'collection_key' => 'videos'],
                ['id' => 4, 'collection_key' => 'personas'],
                ['id' => 5, 'collection_key' => 'cursos'],
            ]]],
            '/cms/languages' => ['data' => ['items' => [['id' => 1, 'code' => 'es']]]],
            '/cms/block-types' => ['data' => ['items' => [
                ['id' => 10, 'block_key' => 'gallery'],
                ['id' => 11, 'block_key' => 'gallery_item'],
                ['id' => 12, 'block_key' => 'document_download'],
            ]]],
            '/cms/entries' => ['data' => ['items' => []]],
            '/events/events' => ['data' => ['items' => []]],
            '/events/occurrences' => ['data' => ['items' => []]],
            default => throw new \RuntimeException("Unexpected migration GET {$path}"),
        };
    }

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload = []): array
    {
        $this->posts[$path] = ($this->posts[$path] ?? 0) + 1;
        $this->payloads[$path][] = $payload;

        return match ($path) {
            '/cms/entries' => ['data' => ['id' => $this->nextCmsId++]],
            '/events/events' => ['data' => ['id' => $this->nextEventId++]],
            '/events/occurrences' => ['data' => ['id' => $this->nextOccurrenceId++]],
            '/events/event-references' => ['data' => ['id' => $this->nextReferenceId++]],
            default => throw new \RuntimeException("Unexpected migration POST {$path}"),
        };
    }

    /** @param array<string, mixed> $fields */
    public function upload(string $path, string $filePath, string $filename, array $fields = []): array
    {
        throw new \RuntimeException('The fixture intentionally has no assets.');
    }

    public function postCount(string $path): int
    {
        return $this->posts[$path] ?? 0;
    }

    /** @return list<array<string, mixed>> */
    public function payloads(string $path): array
    {
        return $this->payloads[$path] ?? [];
    }
}
