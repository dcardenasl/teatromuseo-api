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

    public function testCourseFallsBackToBaseTitleWhenSupplementTitleIsDuplicatedAcrossCourses(): void
    {
        // Reproduces a real legacy data bug found 2026-08-02: 7 sn_cursos rows carry a
        // stale/copy-pasted title shared verbatim across several unrelated courses (e.g. 5
        // different courses all titled "Súbete al Escenario" in the supplement, each with
        // its own correct, distinct sn_escuela.curso_titulo). A supplement title used by
        // only one course is trustworthy and still wins; one reused across several courses
        // in the same slice is treated as a duplication bug, not a real shared name.
        $hash = hash('sha256', 'legacy-course-duplicate-title-fixture');
        $tables = [
            'sn_escuela' => [
                ['curso_id' => '9501', 'curso_titulo' => 'The Logic of Movement', 'curso_descripcion' => 'Desc'],
                ['curso_id' => '9502', 'curso_titulo' => 'La Divina Escuela de Bufones', 'curso_descripcion' => 'Desc'],
                ['curso_id' => '9503', 'curso_titulo' => 'Curso Sin Duplicado', 'curso_descripcion' => 'Desc'],
            ],
            'sn_cursos' => [
                ['id' => '9501', 'title' => 'Súbete al Escenario'],
                ['id' => '9502', 'title' => 'Súbete al Escenario'],
                ['id' => '9503', 'title' => 'Título Único Confiable'],
            ],
            'sn_escuela_img' => [],
            'sn_profesor' => [],
            'sn_categoria_escuela' => [],
        ];
        $client = new LegacyApplyRecordingClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-course-duplicate-title-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/course-duplicate-title-fixture.sql', $hash);

        $service->apply('B', $tables, '/tmp/course-duplicate-title-fixture.sql', $runId);

        $payloads = $client->payloads('/cms/entries');
        $titlesByCourse = [];
        foreach ($payloads as $payload) {
            $titlesByCourse[] = $payload['translations'][0]['title'] ?? null;
        }

        // Duplicated supplement title: falls back to the reliable, distinct base title.
        $this->assertContains('The Logic of Movement', $titlesByCourse);
        $this->assertContains('La Divina Escuela de Bufones', $titlesByCourse);
        $this->assertNotContains('Súbete al Escenario', $titlesByCourse);
        // Unique (non-duplicated) supplement title: still preferred, as before.
        $this->assertContains('Título Único Confiable', $titlesByCourse);
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
                ['id' => 2, 'collection_key' => 'obras'],
                ['id' => 3, 'collection_key' => 'videos'],
                ['id' => 4, 'collection_key' => 'personas'],
                ['id' => 5, 'collection_key' => 'cursos'],
                ['id' => 6, 'collection_key' => 'festivales'],
                ['id' => 7, 'collection_key' => 'noticias'],
                ['id' => 8, 'collection_key' => 'publicaciones'],
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
