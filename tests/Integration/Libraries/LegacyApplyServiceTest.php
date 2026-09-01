<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\LegacyMigration\LegacyApplyService;
use App\Libraries\LegacyMigration\LegacyAssetResolver;
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
        $videoEntry = current(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['translations'][0]['title'] ?? '') === 'Video Fixture'
        ));
        $this->assertSame('published', $videoEntry['workflow_status']);
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

    public function testSliceBSecondPassReusesHistoricalAndCurrentCourseEntriesIndependently(): void
    {
        // sn_escuela (Cursos Históricos) and sn_cursos (Cursos Actuales) are independent
        // legacy tables that happen to share the numeric id '25' by coincidence — they must
        // become two distinct CMS entries, never merged. See applyCourses()'s docblock.
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
                ['id' => '25', 'title' => 'Taller Fixture Actualizado', 'description_text' => 'Descripción suplementaria', 'category_id' => '3', 'date_start' => '2027-01-10', 'date_end' => '2027-01-20'],
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

        // sn_escuela course + sn_profesor teacher + sn_cursos course, all as separate entries.
        $this->assertSame(3, $first['created']['cms_entries']);
        $this->assertSame(0, $first['created']['events']);
        $this->assertSame(0, $second['created']['cms_entries']);
        $this->assertSame(3, $second['reused']['cms_entries']);
        $this->assertSame(0, $client->postCount('/events/events'));

        $historicalEntryId = (int) $repository->findMap('sn_escuela', '25', LegacyMigrationCatalog::TARGET_CMS, 'entry')['target_id'];
        $currentEntryId = (int) $repository->findMap('sn_cursos', '25', LegacyMigrationCatalog::TARGET_CMS, 'entry')['target_id'];
        $this->assertNotSame($historicalEntryId, $currentEntryId);

        $payloads = $client->payloads('/cms/entries');
        $titlesById = [];
        foreach ($payloads as $index => $payload) {
            $titlesById[100 + $index] = $payload['translations'][0]['title'] ?? null;
        }
        // The historical entry keeps its own sn_escuela title — never the coincidentally
        // id-matched sn_cursos title.
        $this->assertSame('Taller Fixture', $titlesById[$historicalEntryId] ?? null);
        // The current-course entry keeps its own sn_cursos title.
        $this->assertSame('Taller Fixture Actualizado', $titlesById[$currentEntryId] ?? null);
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

    public function testHistoricalCoursesNeverInheritDataFromCoincidentallyIdMatchedCurrentCourses(): void
    {
        // Root cause found 2026-08-02 (superseding the old LEGACY-MAP-030 "duplicate title"
        // patch, which only masked a symptom): sn_escuela ("Cursos Históricos") and sn_cursos
        // ("Cursos Actuales") are independent legacy tables with no real relationship — proven
        // by checking all 20 id-coincidental pairs, every one with mismatched dates (often a
        // 5-year gap) and unrelated titles/topics. sn_cursos rows sharing a title across
        // several ids (e.g. 5 different current courses all titled "Súbete al Escenario") is
        // just real duplicate legacy content, not a migration bug — each still becomes its own
        // entry with a title-derived slug that only gains a non-legacy suffix when a collision
        // actually exists. The sn_escuela rows must never read title,
        // description, cover, or any other field from sn_cursos, regardless of any of this.
        $hash = hash('sha256', 'legacy-course-independence-fixture');
        $tables = [
            'sn_escuela' => [
                ['curso_id' => '9501', 'curso_titulo' => 'The Logic of Movement', 'curso_descripcion' => 'Desc histórica'],
                ['curso_id' => '9502', 'curso_titulo' => 'La Divina Escuela de Bufones', 'curso_descripcion' => 'Desc histórica'],
            ],
            'sn_cursos' => [
                ['id' => '9501', 'title' => 'Súbete al Escenario', 'description_text' => 'Desc actual', 'category_id' => '', 'date_start' => '2027-01-10', 'date_end' => '2027-01-20'],
                ['id' => '9502', 'title' => 'Súbete al Escenario', 'description_text' => 'Desc actual', 'category_id' => '', 'date_start' => '2027-02-10', 'date_end' => '2027-02-20'],
            ],
            'sn_escuela_img' => [],
            'sn_profesor' => [],
            'sn_categoria_escuela' => [],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-course-independence-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-independence-fixture.sql', $hash);

        $summary = $service->apply('B', $tables, '/tmp/course-independence-fixture.sql', $runId);

        // 2 historical entries + 2 current entries, all independent (real duplicate content
        // among the current courses does not collapse them into fewer entries).
        $this->assertSame(4, $summary['created']['cms_entries']);

        $payloads = $client->payloads('/cms/entries');
        $titles = array_map(static fn (array $payload): ?string => $payload['translations'][0]['title'] ?? null, $payloads);
        $slugs = array_map(static fn (array $payload): ?string => $payload['translations'][0]['slug'] ?? null, $payloads);

        $this->assertContains('The Logic of Movement', $titles);
        $this->assertContains('La Divina Escuela de Bufones', $titles);
        $this->assertSame(2, count(array_filter($titles, static fn (?string $title): bool => $title === 'Súbete al Escenario')));
        // Both current-course entries share the same title but must not collide on slug.
        $this->assertSame(count(array_unique($slugs)), count($slugs));

        $this->assertSame(
            LegacyMigrationCatalog::MAP_MAPPED,
            $repository->findMap('sn_cursos', '9501', LegacyMigrationCatalog::TARGET_CMS, 'entry')['status']
        );
    }

    public function testCurrentCourseSlugIsDerivedFromTitleWithoutLegacyIdentifierSuffix(): void
    {
        $hash = hash('sha256', 'legacy-current-course-slug-fixture');
        $tables = [
            'sn_escuela' => [],
            'sn_cursos' => [
                [
                    'id' => '9042',
                    'title' => 'Súbete al Escenario - Vacaciones de Invierno',
                    'description_text' => 'Desc actual',
                    'category_id' => '3',
                    'date_start' => '2026-07-15',
                    'date_end' => '2026-07-20',
                    'display' => '1',
                ],
            ],
            'sn_escuela_img' => [],
            'sn_profesor' => [],
            'sn_categoria_escuela' => [],
        ];

        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-current-course-slug-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/current-course-slug-fixture.sql', $hash);

        $summary = $service->apply('B', $tables, '/tmp/current-course-slug-fixture.sql', $runId);

        $this->assertSame(1, $summary['created']['cms_entries']);
        $entryPayload = $client->payloads('/cms/entries')[0];
        $this->assertSame('subete-al-escenario-vacaciones-de-invierno-2026', $entryPayload['translations'][0]['slug']);
    }

    public function testCurrentCourseSlugUsesYearSuffixForDuplicateTitlesAcrossDifferentYears(): void
    {
        $hash = hash('sha256', 'legacy-current-course-year-suffix-fixture');
        $tables = [
            'sn_escuela' => [],
            'sn_cursos' => [
                [
                    'id' => '41',
                    'title' => 'La Escuela de los Nuevos Comediantes',
                    'description_text' => 'Desc 2024',
                    'date_start' => '2024-03-05',
                    'date_end' => '2024-03-20',
                    'display' => '1',
                ],
                [
                    'id' => '42',
                    'title' => 'La Escuela de los Nuevos Comediantes',
                    'description_text' => 'Desc 2025',
                    'date_start' => '2025-03-05',
                    'date_end' => '2025-03-20',
                    'display' => '1',
                ],
                [
                    'id' => '43',
                    'title' => 'La Escuela de los Nuevos Comediantes',
                    'description_text' => 'Desc 2026',
                    'date_start' => '2026-03-05',
                    'date_end' => '2026-03-20',
                    'display' => '1',
                ],
            ],
            'sn_escuela_img' => [],
            'sn_profesor' => [],
            'sn_categoria_escuela' => [],
        ];

        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-current-course-year-suffix-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/current-course-year-suffix-fixture.sql', $hash);

        $summary = $service->apply('B', $tables, '/tmp/current-course-year-suffix-fixture.sql', $runId);

        $this->assertSame(3, $summary['created']['cms_entries']);
        $slugs = array_column($client->payloads('/cms/entries'), 'translations');
        $slugs = array_map(static fn (array $translations): string => $translations[0]['slug'] ?? '', $slugs);

        $this->assertSame([
            'la-escuela-de-los-nuevos-comediantes-2024',
            'la-escuela-de-los-nuevos-comediantes-2025',
            'la-escuela-de-los-nuevos-comediantes-2026',
        ], $slugs);
    }

    public function testCourseCoverIsCopiedFromFirstGalleryImageByDisplayPosition(): void
    {
        // Rule (David, 2026-08-02): sn_escuela ("Cursos Históricos") has no cover field of its
        // own anywhere in the legacy dump — the gallery is the only possible cover source, so
        // the entry's cover must always be a copy of the gallery's first image AS DISPLAYED
        // (ordered by escuela_img_posicion), not whichever row happens to appear first in the
        // dump. Deliberately lists the fixture rows out of display order below to prove the fix
        // sorts before picking, rather than trusting array order.
        $assetRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-course-cover-fixture-' . bin2hex(random_bytes(8));
        mkdir($assetRoot, 0755, true);
        file_put_contents($assetRoot . '/course-gallery-second.jpg', 'position 5 image bytes');
        file_put_contents($assetRoot . '/course-gallery-first.jpg', 'position 2 image bytes');

        try {
            $hash = hash('sha256', 'legacy-course-cover-from-gallery-fixture');
            $tables = [
                'sn_escuela' => [
                    ['curso_id' => '9601', 'curso_titulo' => 'Curso Sin Portada Propia', 'curso_descripcion' => 'Desc'],
                ],
                'sn_cursos' => [],
                'sn_escuela_img' => [
                    // Row with the HIGHER position (5) listed first; the row with the LOWER
                    // position (2) listed second.
                    ['escuela_img_id' => '1', 'escuela_img_url' => 'course-gallery-second.jpg', 'escuela_img_alt' => 'Segunda', 'escuela_img_posicion' => '5', 'curso_id' => '9601'],
                    ['escuela_img_id' => '2', 'escuela_img_url' => 'course-gallery-first.jpg', 'escuela_img_alt' => 'Primera', 'escuela_img_posicion' => '2', 'curso_id' => '9601'],
                ],
                'sn_profesor' => [],
                'sn_categoria_escuela' => [],
            ];

            $resolver = new LegacyAssetResolver($assetRoot);
            $client = new LegacyApplyRecordingClient();
            $client->allowUploads = true;
            $repository = new LegacyMigrationRepository($this->db);
            $service = new LegacyApplyService($repository, $client, $client, $client, $resolver, $hash);
            $runId = $repository->createRun('legacy-course-cover-from-gallery-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-cover-from-gallery-fixture.sql', $hash);

            $service->apply('B', $tables, '/tmp/course-cover-from-gallery-fixture.sql', $runId);

            $entryId = 100; // LegacyApplyRecordingClient mints CMS ids starting at 100.
            $putPayloads = $client->payloads('/cms/entries/' . $entryId);
            $this->assertCount(1, $putPayloads, 'expected exactly one PUT to attach the gallery-derived cover');
            $coverFileId = $putPayloads[0]['translations'][0]['featured_image']['file_id'];

            // course-gallery-first.jpg (position 2) must be uploaded first — and win as the
            // cover — even though it's listed second in the fixture array; position 5's image
            // uploads second and must NOT be picked as the cover.
            $this->assertSame(900, $coverFileId);

            // Re-running with the same inputs must not re-PUT — the ':cover' map row from the
            // first run already points at file 900, so reconcileFeaturedImage() short-circuits
            // before ever issuing a GET or PUT.
            $secondClient = new LegacyApplyRecordingClient();
            $secondClient->allowUploads = true;
            $secondService = new LegacyApplyService($repository, $secondClient, $secondClient, $secondClient, $resolver, $hash);
            $secondRun = $repository->createRun('legacy-course-cover-from-gallery-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-cover-from-gallery-fixture.sql', $hash);
            $secondService->apply('B', $tables, '/tmp/course-cover-from-gallery-fixture.sql', $secondRun);

            $this->assertCount(0, $secondClient->payloads('/cms/entries/' . $entryId), 'cover already correct, no PUT expected on rerun');
        } finally {
            @unlink($assetRoot . '/course-gallery-second.jpg');
            @unlink($assetRoot . '/course-gallery-first.jpg');
            @rmdir($assetRoot);
        }
    }

    public function testCurrentCourseCoverIsAlsoAddedAsGalleryFirstImageWithoutDuplicateUpload(): void
    {
        // Rule (David, 2026-08-02): a course's cover must also exist as the first image of its
        // own gallery. sn_cursos ("Cursos Actuales") has no gallery table of its own — its cover
        // is its only image at all — so applyCurrentCourses() synthesizes a one-item gallery
        // from the SAME resolved cover file, reusing the upload rather than fetching it twice.
        $assetRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-current-course-cover-fixture-' . bin2hex(random_bytes(8));
        mkdir($assetRoot, 0755, true);
        file_put_contents($assetRoot . '/curso-actual-cover.jpg', 'current course cover bytes');

        try {
            $hash = hash('sha256', 'legacy-current-course-cover-gallery-fixture');
            $tables = [
                'sn_escuela' => [],
                'sn_cursos' => [
                    ['id' => '9701', 'title' => 'Curso Actual Con Portada', 'description_text' => 'Desc', 'image_cover' => 'curso-actual-cover.jpg'],
                ],
                'sn_escuela_img' => [],
                'sn_profesor' => [],
                'sn_categoria_escuela' => [],
            ];

            $resolver = new LegacyAssetResolver($assetRoot);
            $client = new LegacyApplyRecordingClient();
            $client->allowUploads = true;
            $repository = new LegacyMigrationRepository($this->db);
            $service = new LegacyApplyService($repository, $client, $client, $client, $resolver, $hash);
            $runId = $repository->createRun('legacy-current-course-cover-gallery-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/current-course-cover-gallery-fixture.sql', $hash);

            $service->apply('B', $tables, '/tmp/current-course-cover-gallery-fixture.sql', $runId);

            $entryId = 100; // LegacyApplyRecordingClient mints CMS ids starting at 100.
            $entryPayload = $client->payloads('/cms/entries')[0];
            $coverFileId = $entryPayload['translations'][0]['featured_image']['file_id'];
            // LegacyApplyRecordingClient mints file ids starting at 900 — asserting the exact
            // value (rather than just "not null") proves this was the ONLY upload in the run:
            // if the gallery step below re-uploaded the same asset instead of reusing it, the
            // gallery item would carry file id 901, not match this one.
            $this->assertSame(900, $coverFileId, 'entry must be created with a cover, and it must be the run\'s only upload');

            $blockPayloads = $client->payloads('/cms/entries/' . $entryId . '/blocks');
            $galleryItemPayload = null;
            foreach ($blockPayloads as $payload) {
                if (($payload['block_config']['image']['file_id'] ?? null) !== null) {
                    $galleryItemPayload = $payload;
                }
            }
            $this->assertNotNull($galleryItemPayload, 'expected a gallery_item block referencing the cover file');
            $this->assertSame($coverFileId, $galleryItemPayload['block_config']['image']['file_id'], 'gallery item must reuse the exact same file id as the cover, not a re-upload');
            $this->assertSame(1, $galleryItemPayload['sort_order'], 'cover-derived gallery item must sort first');

            // Re-running with the same inputs must not create a second gallery item.
            $secondClient = new LegacyApplyRecordingClient();
            $secondClient->allowUploads = true;
            $secondService = new LegacyApplyService($repository, $secondClient, $secondClient, $secondClient, $resolver, $hash);
            $secondRun = $repository->createRun('legacy-current-course-cover-gallery-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/current-course-cover-gallery-fixture.sql', $hash);
            $secondService->apply('B', $tables, '/tmp/current-course-cover-gallery-fixture.sql', $secondRun);

            $this->assertCount(0, $secondClient->payloads('/cms/entries/' . $entryId . '/blocks'), 'gallery item already exists, no duplicate expected on rerun');
        } finally {
            @unlink($assetRoot . '/curso-actual-cover.jpg');
            @rmdir($assetRoot);
        }
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
        $this->assertSame(6, $entries[0]['collection_id']); // 'festivales', not 'cartelera' (id 2)
        $this->assertSame('animate-2024', $entries[0]['translations'][0]['slug']);
        $this->assertSame('IX Encuentro Internacional de Títeres Animate', $entries[0]['translations'][0]['title']);

        $mapped = $repository->findMap('sn_obra', '692', LegacyMigrationCatalog::TARGET_CMS, 'entry');
        $this->assertSame(LegacyMigrationCatalog::MAP_MAPPED, $mapped['status']);
    }

    public function testSliceCMigratesMoreThanTwentyNewsAndMoreThanThirtyPublications(): void
    {
        $hash = hash('sha256', 'legacy-news-scale-fixture');
        // IDs offset into the 900s so they can never collide with legacy_migration_map rows
        // left behind by other tests in this file — that table isn't rolled back between tests.
        $news = [];
        for ($i = 1; $i <= 25; $i++) {
            $news[] = ['id_noticias' => (string) (900 + $i), 'titulo' => 'Noticia ' . $i, 'url' => 'noticia-' . $i, 'lead' => 'Lead', 'cuerpo' => 'Cuerpo'];
        }
        $editorial = [];
        for ($i = 1; $i <= 15; $i++) {
            $editorial[] = ['id' => (string) (900 + $i), 'titulo' => 'Editorial ' . $i, 'url' => 'editorial-' . $i];
        }
        $prensa = [];
        for ($i = 1; $i <= 15; $i++) {
            $prensa[] = ['id' => (string) (930 + $i), 'titulo' => 'Prensa ' . $i, 'url' => 'prensa-' . $i];
        }
        $administracion = [];
        for ($i = 1; $i <= 15; $i++) {
            $administracion[] = ['id' => (string) (960 + $i), 'titulo' => 'Admin ' . $i, 'url' => 'admin-' . $i];
        }
        $tables = [
            'sn_noticias' => $news,
            'sn_editorial' => $editorial,
            'sn_prensa' => $prensa,
            'sn_administracion' => $administracion,
        ];

        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-news-scale-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/news-scale-fixture.sql', $hash);

        $summary = $service->apply('C', $tables, '/tmp/news-scale-fixture.sql', $runId);

        $this->assertSame(25 + 45, $summary['created']['cms_entries']); // 25 news + 45 publications (15+15+15)
    }

    public function testNewsAndPublicationsUseLegacyFechaAsPublishedAtInsteadOfMigrationTimestamp(): void
    {
        // Regression for LEGACY-MAP-034: applyCmsEntry() used to hardcode
        // published_at=null on every create, so the CMS listing's date-order
        // fell back to created_at — the migration run's timestamp, not the
        // real legacy date. sn_noticias.fecha (and sn_editorial/sn_prensa/
        // sn_administracion.fecha) must land in published_at.
        $hash = hash('sha256', 'legacy-published-at-fixture');
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $tables = [
            'sn_noticias' => [
                ['id_noticias' => '990', 'titulo' => 'Noticia con fecha', 'url' => 'noticia-990', 'lead' => 'Lead', 'cuerpo' => 'Cuerpo', 'fecha' => '2019-05-14'],
                // sn_noticias.fecha sometimes holds the date of a future activity the
                // entry announces, not when it was written — must never publish ahead
                // of today or the public listing's published_at <= NOW() gate hides it.
                ['id_noticias' => '992', 'titulo' => 'Noticia de actividad futura', 'url' => 'noticia-992', 'lead' => 'Lead', 'cuerpo' => 'Cuerpo', 'fecha' => $futureDate],
            ],
            'sn_editorial' => [
                ['id' => '991', 'titulo' => 'Editorial con fecha', 'url' => 'editorial-991', 'fecha' => '2018-03-02'],
            ],
        ];

        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-published-at-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/published-at-fixture.sql', $hash);

        $service->apply('C', $tables, '/tmp/published-at-fixture.sql', $runId);

        $entries = $client->payloads('/cms/entries');
        $news = current(array_filter($entries, static fn (array $e): bool => $e['translations'][0]['slug'] === 'noticia-990'));
        $futureNews = current(array_filter($entries, static fn (array $e): bool => $e['translations'][0]['slug'] === 'noticia-992'));
        $editorial = current(array_filter($entries, static fn (array $e): bool => $e['translations'][0]['slug'] === 'editorial-991'));

        $this->assertSame('2019-05-14 00:00:00', $news['published_at']);
        $this->assertSame(date('Y-m-d') . ' 00:00:00', $futureNews['published_at']);
        $this->assertSame('2018-03-02 00:00:00', $editorial['published_at']);
    }

    public function testAppliesPageSliderSlidesForNosotrosAndHistoria(): void
    {
        // LEGACY-MAP-026: categorias 2/3 (Quienes Somos, Historia) got their own hero_slider
        // container added deliberately, once, outside this ETL — this proves applySliderSlides()
        // (generalized from the home-only version) correctly targets those page IDs too.
        $hash = hash('sha256', 'legacy-slider-pages-fixture');
        $tables = [
            'sn_slider' => [
                ['id' => '901', 'archivo' => '/images/slider/nosotros-1.png', 'texto' => 'Museo', 'link' => '', 'display' => '1', 'categoria' => '2'],
                ['id' => '902', 'archivo' => '/images/slider/nosotros-2.png', 'texto' => 'Museo', 'link' => '', 'display' => '1', 'categoria' => '2'],
                ['id' => '903', 'archivo' => '/images/slider/historia-1.png', 'texto' => '', 'link' => '', 'display' => '1', 'categoria' => '3'],
                // Different category and an invisible row must never leak into either page.
                ['id' => '904', 'archivo' => '/images/slider/animate-1.png', 'texto' => '', 'link' => '', 'display' => '1', 'categoria' => '5'],
                ['id' => '905', 'archivo' => '/images/slider/hidden.png', 'texto' => '', 'link' => '', 'display' => '0', 'categoria' => '2'],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-slider-pages-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/slider-pages-fixture.sql', $hash);

        $summary = $service->apply('C', $tables, '/tmp/slider-pages-fixture.sql', $runId);

        $this->assertSame(3, $summary['created']['blocks']); // 2 nosotros slides + 1 historia slide
        $pageBlockPayloads = $client->payloads('/cms/pages/17/blocks');
        $this->assertCount(2, $pageBlockPayloads);
        $historiaPayloads = $client->payloads('/cms/pages/18/blocks');
        $this->assertCount(1, $historiaPayloads);
    }

    public function testFestivalGalleryTargetMissingRecordsIssueInsteadOfCrashing(): void
    {
        // The fake client's /cms/entries always returns no items (matching the dedup-check
        // needs of every other test here), so findCmsEntry() can never "find" upa-chalupa-2019
        // in this harness — this proves that a missing festival entry degrades to a recorded
        // issue instead of an uncaught exception. The positive "gallery actually attaches" path
        // is verified live against a real cms-domain run (LEGACY-MAP-026 apply).
        $hash = hash('sha256', 'legacy-festival-gallery-fixture');
        $tables = [
            'sn_slider' => [
                ['id' => '910', 'archivo' => '/images/slider/upa-1.png', 'texto' => '', 'link' => '', 'display' => '1', 'categoria' => '4'],
            ],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-festival-gallery-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/festival-gallery-fixture.sql', $hash);

        $summary = $service->apply('C', $tables, '/tmp/festival-gallery-fixture.sql', $runId);

        $this->assertGreaterThanOrEqual(1, $summary['issues']);
        $this->assertSame(0, $summary['created']['blocks']);
    }

    public function testReconcilesFeaturedImageOnceAssetBecomesAvailableInALaterRun(): void
    {
        // Reproduces the real LEGACY-MAP-024/028 gap: an entry (and its paired event — the
        // public Cartelera listing reads events.cover_file_id/gallery_file_ids directly, not
        // the CMS entry's featured_image/gallery_item blocks) is created successfully while its
        // cover and gallery uploads fail (rate limit, transient error). A later run must
        // retroactively attach both to the entry and the event once the assets resolve, without
        // duplicating either, and must not re-PUT once everything is already correct.
        $assetRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-cover-fixture-' . bin2hex(random_bytes(8));
        mkdir($assetRoot, 0755, true);
        file_put_contents($assetRoot . '/obra-fixture.jpg', 'fixture image bytes');
        file_put_contents($assetRoot . '/gallery-fixture.jpg', 'fixture gallery bytes');

        try {
            $hash = hash('sha256', 'legacy-cover-reconcile-fixture');
            // legacy_migration_map isn't rolled back between tests in this file (see
            // testWorksAndCompaniesScaleWithoutHardcodedCaps) — id_obra/id_slider must be
            // unique across the whole class, not just this test.
            $tables = [
                'sn_obra' => [
                    [
                        'id_obra' => '95001',
                        'titulo_obra' => 'Obra Fixture',
                        'url' => 'obra-fixture',
                        'fecha_obra' => '2026-08-01',
                        'hora_obra' => '20:00',
                        'valor1_obra' => '$ 3.000',
                        'valor2_obra' => '$ 2.500',
                        'direccion_obra' => 'Teatromuseo',
                        'id_publico' => '',
                        'id_compania' => '',
                        'foto_obra' => 'obra-fixture.jpg',
                        'display' => '1',
                    ],
                ],
                'sn_slider_cartelera' => [
                    ['id_slider' => '95001', 'id_obra' => '95001', 'url_sl' => 'gallery-fixture.jpg', 'alt_text' => 'Gallery Fixture', 'display' => '1'],
                ],
            ];
            $repository = new LegacyMigrationRepository($this->db);
            $entryId = 100; // LegacyApplyRecordingClient mints CMS ids starting at 100.
            $eventId = 200; // ...and event ids starting at 200.

            // Run 1: no asset resolver configured — mirrors an upload that failed on the spot.
            // The entry and its event are both created without a cover.
            $firstClient = new LegacyApplyRecordingClient();
            $firstService = new LegacyApplyService($repository, $firstClient, $firstClient, $firstClient, null, $hash);
            $firstRun = $repository->createRun('legacy-cover-reconcile-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/cover-fixture.sql', $hash);
            $first = $firstService->apply('A', $tables, '/tmp/cover-fixture.sql', $firstRun);
            $repository->finishRun($firstRun, LegacyMigrationCatalog::RUN_COMPLETED, $first);

            $this->assertSame(1, $first['created']['cms_entries']);
            $this->assertSame(1, $first['created']['events']);
            $this->assertNull($firstClient->payloads('/events/events')[0]['cover_file_id']);
            $this->assertCount(0, $firstClient->payloads('/events/events/' . $eventId), 'no gallery asset resolved yet, so no PUT expected');

            // Run 2: the asset now resolves and the upload succeeds. The entry already exists
            // (found via the control-plane map), so it must reach reconcileFeaturedImage()
            // rather than the create path, and PUT the now-available cover.
            $resolver = new LegacyAssetResolver($assetRoot);
            $secondClient = new LegacyApplyRecordingClient();
            $secondClient->allowUploads = true;
            $secondClient->entryDetails[$entryId] = [
                'id' => $entryId,
                'translations' => [['language_id' => 1, 'slug' => 'obra-fixture', 'title' => 'Obra Fixture', 'featured_image' => ['file_id' => null]]],
            ];
            $secondService = new LegacyApplyService($repository, $secondClient, $secondClient, $secondClient, $resolver, $hash);
            $secondRun = $repository->createRun('legacy-cover-reconcile-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/cover-fixture.sql', $hash);
            $second = $secondService->apply('A', $tables, '/tmp/cover-fixture.sql', $secondRun);
            $repository->finishRun($secondRun, LegacyMigrationCatalog::RUN_COMPLETED, $second);

            $this->assertSame(0, $second['created']['cms_entries']);
            $this->assertSame(1, $second['reused']['cms_entries']);
            $putPayloads = $secondClient->payloads('/cms/entries/' . $entryId);
            $this->assertCount(1, $putPayloads, 'expected exactly one PUT to attach the now-available cover');
            $this->assertSame(900, $putPayloads[0]['translations'][0]['featured_image']['file_id']);

            // The cover asset (obra-fixture.jpg) resolves first (before the CMS entry is
            // built), so it claims file id 900; the gallery image (gallery-fixture.jpg)
            // resolves afterwards inside applyGallery() and claims 901.
            $eventPutPayloads = $secondClient->payloads('/events/events/' . $eventId);
            $this->assertCount(2, $eventPutPayloads, 'expected one PUT for the event cover and one for its gallery');
            $this->assertSame(['cover_file_id' => 900], $eventPutPayloads[0]);
            $this->assertSame(['gallery_file_ids' => '901'], $eventPutPayloads[1]);

            // Run 3: identical inputs again — the cover is already correct, so no further PUT.
            $thirdClient = new LegacyApplyRecordingClient();
            $thirdClient->allowUploads = true;
            $thirdClient->entryDetails[$entryId] = [
                'id' => $entryId,
                'translations' => [['language_id' => 1, 'slug' => 'obra-fixture', 'title' => 'Obra Fixture', 'featured_image' => ['file_id' => 900]]],
            ];
            $thirdService = new LegacyApplyService($repository, $thirdClient, $thirdClient, $thirdClient, $resolver, $hash);
            $thirdRun = $repository->createRun('legacy-cover-reconcile-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/cover-fixture.sql', $hash);
            $third = $thirdService->apply('A', $tables, '/tmp/cover-fixture.sql', $thirdRun);
            $repository->finishRun($thirdRun, LegacyMigrationCatalog::RUN_COMPLETED, $third);

            $this->assertCount(0, $thirdClient->payloads('/cms/entries/' . $entryId), 'idempotent: cover already correct, no re-PUT expected');
            $this->assertCount(0, $thirdClient->payloads('/events/events/' . $eventId), 'idempotent: event cover already correct, no re-PUT expected');
        } finally {
            @unlink($assetRoot . '/obra-fixture.jpg');
            @rmdir($assetRoot);
        }
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
    private int $nextBlockId = 600;
    /** Tests can seed this to control what reconcileFeaturedImage() sees via GET /cms/entries/{id}. @var array<int, array<string, mixed>> */
    public array $entryDetails = [];
    /** Default upload() always throws (matches every existing test's "no assets" fixture) — flip this to simulate an asset upload succeeding on a later run. */
    public bool $allowUploads = false;
    private int $nextFileId = 900;

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): array
    {
        if (in_array($path, ['/cms/block-types', '/events/events', '/events/occurrences'], true)) {
            if ((int) ($query['per_page'] ?? 0) > 100) {
                throw new \RuntimeException('Migration exceeded a public domain pagination contract.');
            }
        }

        // Simulates a page that already has a seeded hero_slider container (the real
        // nosotros/historia containers were created once, deliberately, outside this ETL —
        // see LEGACY-MAP-026) — every fixture page "has" one so applySliderSlides() can find it.
        if (preg_match('#^/cms/pages/(\d+)/blocks$#', $path, $matches) === 1) {
            return ['data' => ['items' => [
                ['id' => 1000 + (int) $matches[1], 'block_id' => 13, 'parent_instance_id' => 0],
            ]]];
        }

        // applyGallery()'s findCmsBlock() lookup for an entry-owned gallery container —
        // no fixture entry pre-seeds one, so it's always empty (forces the create path).
        if (preg_match('#^/cms/entries/(\d+)/blocks$#', $path) === 1) {
            return ['data' => ['items' => []]];
        }

        if (preg_match('#^/cms/entries/(\d+)$#', $path, $matches) === 1) {
            $id = (int) $matches[1];

            return ['data' => $this->entryDetails[$id] ?? [
                'id' => $id,
                'translations' => [['language_id' => 1, 'slug' => 'x', 'title' => 'X', 'featured_image' => ['file_id' => null]]],
            ]];
        }

        return match ($path) {
            '/cms/collections' => ['data' => ['items' => [
                ['id' => 1, 'collection_key' => 'companias'],
                ['id' => 2, 'collection_key' => 'cartelera'],
                ['id' => 3, 'collection_key' => 'videos'],
                ['id' => 4, 'collection_key' => 'personas'],
                ['id' => 5, 'collection_key' => 'cursos'],
                ['id' => 9, 'collection_key' => 'teatroescuela'],
                ['id' => 6, 'collection_key' => 'festivales'],
                ['id' => 7, 'collection_key' => 'noticias'],
                ['id' => 8, 'collection_key' => 'editoriales'],
                ['id' => 15, 'collection_key' => 'prensa'],
                ['id' => 16, 'collection_key' => 'transparencia'],
            ]]],
            '/cms/languages' => ['data' => ['items' => [['id' => 1, 'code' => 'es']]]],
            '/cms/block-types' => ['data' => ['items' => [
                ['id' => 10, 'block_key' => 'gallery'],
                ['id' => 11, 'block_key' => 'gallery_item'],
                ['id' => 12, 'block_key' => 'document_download'],
                ['id' => 13, 'block_key' => 'hero_slider'],
                ['id' => 14, 'block_key' => 'slide_banner'],
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

        if (preg_match('#^/cms/(pages|entries)/\d+/blocks$#', $path) === 1) {
            return ['data' => ['id' => $this->nextBlockId++]];
        }

        return match ($path) {
            '/cms/entries' => ['data' => ['id' => $this->nextCmsId++]],
            '/events/events' => ['data' => ['id' => $this->nextEventId++]],
            '/events/occurrences' => ['data' => ['id' => $this->nextOccurrenceId++]],
            '/events/event-references' => ['data' => ['id' => $this->nextReferenceId++]],
            '/cms/submissions/import' => ['data' => ['id' => $this->nextSubmissionId++]],
            default => throw new \RuntimeException("Unexpected migration POST {$path}"),
        };
    }

    /** @param array<string, mixed> $payload */
    public function put(string $path, array $payload = []): array
    {
        $this->posts[$path] = ($this->posts[$path] ?? 0) + 1;
        $this->payloads[$path][] = $payload;

        if (preg_match('#^/cms/entries/(\d+)$#', $path, $matches) === 1) {
            return ['data' => ['id' => (int) $matches[1]]];
        }

        if (preg_match('#^/events/events/(\d+)$#', $path, $matches) === 1) {
            return ['data' => ['id' => (int) $matches[1]]];
        }

        if (preg_match('#^/events/occurrences/(\d+)$#', $path, $matches) === 1) {
            return ['data' => ['id' => (int) $matches[1]]];
        }

        throw new \RuntimeException("Unexpected migration PUT {$path}");
    }

    /** @param array<string, mixed> $fields */
    public function upload(string $path, string $filePath, string $filename, array $fields = []): array
    {
        if (! $this->allowUploads) {
            throw new \RuntimeException('The fixture intentionally has no assets.');
        }

        return ['data' => ['id' => $this->nextFileId++]];
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
