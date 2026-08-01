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

    public function testSliceAMigratesMoreThanTenWorksAndExcludesKnownTestRows(): void
    {
        $hash = hash('sha256', 'legacy-work-scale-fixture');
        // IDs offset into the 900s so they can never collide with legacy_migration_map rows
        // left behind by other tests in this file — that table isn't rolled back between tests.
        $companies = [];
        for ($c = 1; $c <= 4; $c++) {
            $companies[] = ['id_compania' => (string) (900 + $c), 'nombre_compania' => 'Compañía ' . $c, 'display_comp' => '1'];
        }
        $works = [];
        for ($i = 1; $i <= 12; $i++) {
            $works[] = [
                'id_obra' => (string) (900 + $i),
                'titulo_obra' => 'Obra ' . $i,
                'url' => 'obra-' . $i,
                'fecha_obra' => '2026-08-01',
                'hora_obra' => '20:00',
                'valor1_obra' => '$ 1.000',
                'valor2_obra' => '$ 800',
                'direccion_obra' => 'Teatromuseo',
                'id_publico' => '1',
                'id_compania' => (string) (900 + (($i % 4) + 1)),
                'display' => '1',
            ];
        }
        // LEGACY-MAP-022/024: confirmed junk rows must never become entries, even unlimited.
        $works[] = ['id_obra' => '9001', 'titulo_obra' => 'Test', 'url' => 'test-a', 'display' => '1'];
        $works[] = ['id_obra' => '9002', 'titulo_obra' => 'TEst', 'url' => 'test-b', 'display' => '1'];

        $videos = [];
        for ($v = 1; $v <= 6; $v++) {
            $videos[] = ['id_youtube' => (string) (900 + $v), 'url' => 'video-' . $v, 'nombre' => 'Video ' . $v, 'fecha' => '2026-08-02', 'display' => '1'];
        }

        $tables = [
            'sn_compania' => $companies,
            'sn_obra' => $works,
            'sn_slider_cartelera' => [],
            'sn_youtube' => $videos,
            'sn_publico' => [['id_publico' => '1', 'nombre_publico' => 'Familiar']],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-work-scale-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/work-scale-fixture.sql', $hash);

        $summary = $service->apply('A', $tables, '/tmp/work-scale-fixture.sql', $runId);

        $this->assertSame(12 + 4 + 6, $summary['created']['cms_entries']); // works + companies + videos
        $this->assertSame(12, $summary['created']['events']);
        $this->assertNull($repository->findMap('sn_obra', '9001', LegacyMigrationCatalog::TARGET_CMS, 'entry'));
        $this->assertNull($repository->findMap('sn_obra', '9002', LegacyMigrationCatalog::TARGET_CMS, 'entry'));
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

    public function testSliceBMigratesMoreThanThreeCoursesAndMoreThanTwentyTeachers(): void
    {
        $hash = hash('sha256', 'legacy-course-scale-fixture');
        $courses = [];
        $teachers = [];
        // IDs offset into the 900s so they can never collide with legacy_migration_map rows
        // left behind by other tests in this file (e.g. profesor_id='8', curso_id='25') — that
        // table isn't rolled back between tests here.
        for ($i = 1; $i <= 5; $i++) {
            $courses[] = ['curso_id' => (string) (900 + $i), 'curso_titulo' => 'Curso ' . $i, 'curso_descripcion' => 'Desc'];
        }
        for ($i = 1; $i <= 25; $i++) {
            // Spread teachers across the 5 courses so all 25 are eligible — LEGACY-MAP-023
            // removed both the 3-course and 20-teacher caps for the full migration.
            $teachers[] = ['profesor_id' => (string) (900 + $i), 'profesor_nombre' => 'Docente ' . $i, 'profesor_curso' => (string) (900 + ($i % 5) + 1)];
        }
        $tables = ['sn_escuela' => $courses, 'sn_profesor' => $teachers, 'sn_cursos' => [], 'sn_escuela_img' => [], 'sn_categoria_escuela' => []];

        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-course-scale-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-scale-fixture.sql', $hash);

        $summary = $service->apply('B', $tables, '/tmp/course-scale-fixture.sql', $runId);

        $this->assertSame(30, $summary['created']['cms_entries']); // 5 courses + 25 teachers
    }

    public function testSliceDSecondPassReusesFormSubmissionsAndPreservesHistoricalDate(): void
    {
        $hash = hash('sha256', 'legacy-contact-fixture');
        $tables = [
            'sn_contact_message' => [
                [
                    'id' => '16',
                    'date_send' => '2024-07-11 16:54:23',
                    'name_contact' => 'Silvana Vargas',
                    'email_address' => 'svargas@example.cl',
                    'phone_number' => '949019332',
                    'message_text' => 'Hola, quisiera cotizar una visita.',
                    'status_id' => '2',
                    'ip_address' => null,
                    'user_agent' => null,
                ],
                [
                    'id' => '17',
                    'date_send' => '2024-07-18 09:27:17',
                    'name_contact' => 'Daniele Lupi',
                    'email_address' => 'daniele@example.it',
                    'phone_number' => '2147483647',
                    'message_text' => 'Buen dia',
                    'status_id' => '1',
                    'ip_address' => '190.0.0.1',
                    'user_agent' => 'Mozilla/5.0',
                ],
            ],
            'sn_contact_status' => [
                ['id' => '1', 'title' => 'PENDIENTE'],
                ['id' => '2', 'title' => 'COMPLETADA'],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);

        $firstRun = $repository->createRun('legacy-contact-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/contact-fixture.sql', $hash);
        $first = $service->apply('D', $tables, '/tmp/contact-fixture.sql', $firstRun);
        $repository->finishRun($firstRun, LegacyMigrationCatalog::RUN_COMPLETED, $first);

        $secondRun = $repository->createRun('legacy-contact-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/contact-fixture.sql', $hash);
        $second = $service->apply('D', $tables, '/tmp/contact-fixture.sql', $secondRun);
        $repository->finishRun($secondRun, LegacyMigrationCatalog::RUN_COMPLETED, $second);

        $this->assertSame(2, $first['created']['form_submissions']);
        $this->assertSame(0, $second['created']['form_submissions']);
        $this->assertSame(2, $second['reused']['form_submissions']);

        $payloads = $client->payloads('/cms/submissions/import');
        $this->assertCount(2, $payloads);
        $this->assertSame('2024-07-11 16:54:23', $payloads[0]['created_at']);
        $this->assertSame('replied', $payloads[0]['status']);
        $this->assertSame('Silvana Vargas', $payloads[0]['form_data']['name']);
        $this->assertSame('949019332', $payloads[0]['form_data']['phone']);
        $this->assertNull($payloads[0]['ip_address']);
        $this->assertSame('new', $payloads[1]['status']);
        $this->assertSame('190.0.0.1', $payloads[1]['ip_address']);

        $mapped = $repository->findMap('sn_contact_message', '16', LegacyMigrationCatalog::TARGET_CMS, 'form_submission');
        $this->assertSame(LegacyMigrationCatalog::MAP_MAPPED, $mapped['status']);
    }

    public function testSliceCSendsAnimateEditionToFestivalesNotObras(): void
    {
        $hash = hash('sha256', 'legacy-animate-fixture');
        $tables = [
            'sn_obra' => [
                [
                    'id_obra' => '692',
                    'titulo_obra' => 'Animate',
                    'hora_obra' => '19:30 Hrs',
                    'fecha_obra' => '2024-11-02',
                    'valor1_obra' => 'Gratuito',
                    'valor2_obra' => 'Gratuito',
                    'direccion_obra' => 'Cárcel 471, Valparaíso',
                    'foto_obra' => '/images/cartelera/full/2-nov.png',
                    'descripcion_corta_obra' => 'IX Encuentro Internacional de Títeres Animate',
                    'descripcion_larga_obra' => 'IX Encuentro Internacional de Títeres Animate',
                    'id_publico' => '3',
                    'id_compania' => '30',
                    'display' => '1',
                    'url' => 'animate',
                ],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);

        $firstRun = $repository->createRun('legacy-animate-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/animate-fixture.sql', $hash);
        $first = $service->apply('C', $tables, '/tmp/animate-fixture.sql', $firstRun);
        $repository->finishRun($firstRun, LegacyMigrationCatalog::RUN_COMPLETED, $first);

        $secondRun = $repository->createRun('legacy-animate-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/animate-fixture.sql', $hash);
        $second = $service->apply('C', $tables, '/tmp/animate-fixture.sql', $secondRun);
        $repository->finishRun($secondRun, LegacyMigrationCatalog::RUN_COMPLETED, $second);

        $this->assertSame(1, $first['created']['cms_entries']);
        $this->assertSame(0, $second['created']['cms_entries']);
        $this->assertSame(1, $second['reused']['cms_entries']);

        $entries = $client->payloads('/cms/entries');
        $this->assertCount(1, $entries);
        $this->assertSame(6, $entries[0]['collection_id']); // 'festivales', not 'obras' (id 2)
        $this->assertSame('animate-2024', $entries[0]['translations'][0]['slug']);
        $this->assertSame('IX Encuentro Internacional de Títeres Animate', $entries[0]['translations'][0]['title']);

        $mapped = $repository->findMap('sn_obra', '692', LegacyMigrationCatalog::TARGET_CMS, 'entry');
        $this->assertSame(LegacyMigrationCatalog::MAP_MAPPED, $mapped['status']);
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
    private int $nextSubmissionId = 500;

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
                ['id' => 6, 'collection_key' => 'festivales'],
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
            '/cms/submissions/import' => ['data' => ['id' => $this->nextSubmissionId++]],
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
