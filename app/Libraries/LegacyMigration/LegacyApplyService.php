<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

use DateTimeImmutable;

/**
 * Applies the selected legacy slice through the public domain APIs.
 *
 * The hub owns only the mapping/control tables; editorial and event records
 * are always created through their own domains. Every create operation first
 * consults the control map and then uses a deterministic lookup key as a
 * second recovery mechanism for an interrupted retry.
 */
final class LegacyApplyService
{
    /** @var array<string, int> */
    private array $collections = [];
    /** @var array<string, int> */
    private array $languages = [];
    /** @var array<string, int> */
    private array $blockTypes = [];
    /** @var array<string, mixed> */
    private array $summary = [];
    private int $currentRunId = 0;

    public function __construct(
        private readonly LegacyMigrationRepository $repository,
        private readonly LegacyDomainClientInterface $cms,
        private readonly LegacyDomainClientInterface $event,
        private readonly LegacyDomainClientInterface $hub,
        private readonly ?LegacyAssetResolver $assetResolver = null,
        private readonly string $sourceHash = ''
    ) {
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     * @return array<string, mixed>
     */
    public function apply(string $slice, array $tables, string $sourcePath, int $runId): array
    {
        $this->currentRunId = $runId;
        $this->summary = [
            'slice' => $slice,
            'mode' => LegacyMigrationCatalog::MODE_APPLY,
            'source' => ['path' => $sourcePath, 'sha256' => $this->sourceHash],
            'created' => ['cms_entries' => 0, 'events' => 0, 'occurrences' => 0, 'blocks' => 0, 'files' => 0, 'references' => 0],
            'reused' => ['cms_entries' => 0, 'events' => 0, 'occurrences' => 0, 'blocks' => 0, 'files' => 0, 'references' => 0],
            'issues' => 0,
        ];

        $this->loadCmsCatalog();
        if ($slice === 'B') {
            $this->applyCourses($tables, $runId);
        } else {
            $this->applyWorks($tables, $runId);
        }

        return $this->summary;
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyWorks(array $tables, int $runId): void
    {
        $workRows = $this->visibleRows($tables['sn_obra'] ?? [], 'display');
        usort($workRows, fn (array $left, array $right): int => $this->numericId($left, 'id_obra') <=> $this->numericId($right, 'id_obra'));
        $workRows = array_slice($workRows, 0, 10);

        $companyRows = $this->visibleRows($tables['sn_compania'] ?? [], 'display_comp');
        $referencedCompanyIds = [];
        foreach ($workRows as $work) {
            $companyId = $this->stringValue($work['id_compania'] ?? '');
            if ($companyId !== '' && ! isset($referencedCompanyIds[$companyId]) && count($referencedCompanyIds) < 3) {
                $referencedCompanyIds[$companyId] = true;
            }
        }
        foreach ($companyRows as $company) {
            $companyId = $this->stringValue($company['id_compania'] ?? '');
            if ($companyId !== '' && count($referencedCompanyIds) < 3) {
                $referencedCompanyIds[$companyId] = true;
            }
        }
        $companyTargets = [];
        foreach ($companyRows as $company) {
            $id = $this->stringValue($company['id_compania'] ?? '');
            if ($id === '' || ! isset($referencedCompanyIds[$id])) {
                continue;
            }
            $slug = $this->slug($this->stringValue($company['nombre_compania'] ?? 'compania-' . $id));
            $companyTargets[$id] = $this->applyCmsEntry(
                'sn_compania',
                $id,
                'companias',
                $slug,
                $this->stringValue($company['nombre_compania'] ?? 'Compañía'),
                $this->stringValue($company['resena_compania'] ?? ''),
                ['name' => $this->stringValue($company['nombre_compania'] ?? ''), 'summary' => $this->stringValue($company['resena_compania'] ?? ''), 'description' => $this->stringValue($company['resena_compania'] ?? '')],
                $runId
            );
        }

        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        foreach ($workRows as $work) {
            $groups[$this->workKey($work)][] = $work;
        }

        foreach ($groups as $workKey => $group) {
            $canonical = $group[0];
            $canonicalId = $this->stringValue($canonical['id_obra'] ?? '');
            if ($canonicalId === '') {
                $this->summary['issues']++;
                continue;
            }
            $companyId = $this->stringValue($canonical['id_compania'] ?? '');
            $entryId = $this->applyCmsEntry(
                'sn_obra',
                $canonicalId,
                'obras',
                $workKey,
                $this->stringValue($canonical['titulo_obra'] ?? 'Obra'),
                $this->stringValue($canonical['descripcion_larga_obra'] ?? $canonical['descripcion_corta_obra'] ?? ''),
                [
                    'subtitle' => $this->stringValue($canonical['descripcion_corta_obra'] ?? ''),
                    'synopsis' => $this->stringValue($canonical['descripcion_larga_obra'] ?? ''),
                    'premiere_date' => $this->validDate($canonical['fecha_obra'] ?? null) ? $this->stringValue($canonical['fecha_obra']) : null,
                    'company' => isset($companyTargets[$companyId]) ? ['entry_id' => $companyTargets[$companyId], 'collection_key' => 'companias'] : null,
                ],
                $runId,
                $this->assetFile('sn_obra', $canonicalId, $canonical['foto_obra'] ?? null, 'obra-' . $canonicalId, $runId)
            );

            foreach (array_slice($group, 1) as $duplicate) {
                $duplicateId = $this->stringValue($duplicate['id_obra'] ?? '');
                if ($duplicateId !== '') {
                    $this->map($runId, 'sn_obra', $duplicateId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $entryId, LegacyMigrationCatalog::MAP_DUPLICATE, 'canonical work ' . $canonicalId);
                }
            }

            $eventId = $this->applyEvent($canonical, $workKey, $entryId, $runId);
            foreach (array_slice($group, 1) as $duplicate) {
                $duplicateId = $this->stringValue($duplicate['id_obra'] ?? '');
                if ($duplicateId !== '') {
                    $this->map($runId, 'sn_obra', $duplicateId, LegacyMigrationCatalog::TARGET_EVENT, 'event', (string) $eventId, LegacyMigrationCatalog::MAP_DUPLICATE, 'canonical event ' . $canonicalId);
                }
            }
            foreach ($group as $work) {
                $workId = $this->stringValue($work['id_obra'] ?? '');
                if ($workId === '') {
                    continue;
                }
                $this->applyOccurrence($work, $eventId, $workId, $runId);
            }

            $groupIds = array_fill_keys(array_map(fn (array $item): string => $this->stringValue($item['id_obra'] ?? ''), $group), true);
            $images = array_values(array_filter(
                $this->visibleRows($tables['sn_slider_cartelera'] ?? [], 'display'),
                fn (array $image): bool => isset($groupIds[$this->stringValue($image['id_obra'] ?? '')])
            ));
            $this->applyGallery('sn_obra', $canonicalId, (int) $entryId, $images, $runId, 'url_sl', 'id_slider', 'alt_text');
        }

        $videoGroups = [];
        foreach ($this->visibleRows($tables['sn_youtube'] ?? [], 'display') as $video) {
            $key = $this->stringValue($video['url'] ?? '');
            if ($key !== '') {
                $videoGroups[$key][] = $video;
            }
        }
        foreach (array_slice($videoGroups, 0, 5, true) as $videoKey => $group) {
            $video = $group[0];
            $videoId = $this->stringValue($video['id_youtube'] ?? '');
            if ($videoId === '') {
                continue;
            }
            $videoEntryId = $this->applyCmsEntry(
                'sn_youtube',
                $videoId,
                'videos',
                $this->slug($videoKey),
                $this->stringValue($video['nombre'] ?? 'Video de YouTube'),
                $this->stringValue($video['nombre'] ?? ''),
                ['provider' => 'youtube', 'video_id' => $this->youtubeId($videoKey), 'video_url' => $this->youtubeUrl($videoKey)],
                $runId
            );
            foreach (array_slice($group, 1) as $duplicate) {
                $duplicateId = $this->stringValue($duplicate['id_youtube'] ?? '');
                if ($duplicateId !== '') {
                    $this->map($runId, 'sn_youtube', $duplicateId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $videoEntryId, LegacyMigrationCatalog::MAP_DUPLICATE, 'canonical video ' . $videoId);
                }
            }
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyCourses(array $tables, int $runId): void
    {
        $courses = $this->visibleRows($tables['sn_escuela'] ?? [], 'curso_display');
        usort($courses, fn (array $left, array $right): int => $this->numericId($left, 'curso_id') <=> $this->numericId($right, 'curso_id'));
        $courses = array_slice($courses, 0, 3);
        $supplements = [];
        foreach ($tables['sn_cursos'] ?? [] as $supplement) {
            $id = $this->stringValue($supplement['id'] ?? '');
            if ($id !== '') {
                $supplements[$id] = $supplement;
            }
        }
        $categoryTitles = [];
        foreach ($tables['sn_categoria_escuela'] ?? [] as $category) {
            $categoryId = $this->stringValue($category['id'] ?? '');
            $categoryTitle = $this->stringValue($category['titulo'] ?? '');
            if ($categoryId !== '' && $categoryTitle !== '') {
                $categoryTitles[$categoryId] = $categoryTitle;
            }
        }

        $teachers = [];
        $selectedCourseIds = array_fill_keys(array_map(
            fn (array $course): string => $this->stringValue($course['curso_id'] ?? ''),
            $courses
        ), true);
        $teacherRows = $this->visibleRows($tables['sn_profesor'] ?? [], 'profesor_display');
        usort($teacherRows, fn (array $left, array $right): int => $this->numericId($left, 'profesor_id') <=> $this->numericId($right, 'profesor_id'));
        foreach ($teacherRows as $teacher) {
            if (! isset($selectedCourseIds[$this->stringValue($teacher['profesor_curso'] ?? '')]) || count($teachers) >= 20) {
                continue;
            }
            $teacherId = $this->stringValue($teacher['profesor_id'] ?? '');
            if ($teacherId !== '' && ! isset($teachers[$teacherId])) {
                $teachers[$teacherId] = $this->applyCmsEntry(
                    'sn_profesor',
                    $teacherId,
                    'personas',
                    $this->slug($this->stringValue($teacher['profesor_nombre'] ?? 'persona')) . '-' . $teacherId,
                    $this->stringValue($teacher['profesor_nombre'] ?? 'Persona'),
                    $this->stringValue($teacher['profesor_nacionalidad'] ?? ''),
                    ['name' => $this->stringValue($teacher['profesor_nombre'] ?? ''), 'role' => 'Docente', 'bio' => $this->stringValue($teacher['profesor_nacionalidad'] ?? '')],
                    $runId
                );
            }
        }

        foreach ($courses as $course) {
            $courseId = $this->stringValue($course['curso_id'] ?? '');
            if ($courseId === '') {
                continue;
            }
            $supplement = $supplements[$courseId] ?? [];
            $key = $this->courseKey($course);
            $instructorIds = [];
            foreach ($this->visibleRows($tables['sn_profesor'] ?? [], 'profesor_display') as $teacher) {
                if ($this->stringValue($teacher['profesor_curso'] ?? '') === $courseId) {
                    $teacherId = $this->stringValue($teacher['profesor_id'] ?? '');
                    if ($teacherId !== '' && isset($teachers[$teacherId])) {
                        $instructorIds[] = ['entry_id' => $teachers[$teacherId], 'collection_key' => 'personas'];
                    }
                }
            }
            $courseTitle = $this->stringValue($supplement['title'] ?? '');
            if ($courseTitle === '') {
                $courseTitle = $this->stringValue($course['curso_titulo'] ?? 'Curso');
            }
            $courseDescription = $this->stringValue($supplement['description_text'] ?? '');
            if ($courseDescription === '') {
                $courseDescription = $this->stringValue($course['curso_descripcion'] ?? '');
            }
            $entryId = $this->applyCmsEntry(
                'sn_escuela',
                $courseId,
                'cursos',
                $key,
                $courseTitle,
                $courseDescription,
                [
                    'category' => $categoryTitles[$this->stringValue($course['curso_categoria'] ?? '')] ?? '',
                    'modality' => 'presencial',
                    'start_date' => $this->validDate($course['curso_fecha_inicio'] ?? null) ? $this->stringValue($course['curso_fecha_inicio']) : null,
                    'end_date' => $this->validDate($course['curso_fecha_termino'] ?? null) ? $this->stringValue($course['curso_fecha_termino']) : null,
                    'schedule' => $this->timeRange($course['curso_hora_inicio'] ?? null, $course['curso_hora_termino'] ?? null),
                    'days' => $this->stringValue($course['curso_dias'] ?? ''),
                    'duration' => $this->stringValue($course['curso_duracion'] ?? ''),
                    'venue' => $this->stringValue($course['curso_direccion'] ?? ''),
                    'capacity' => $this->integerOrNull($course['curso_cupos'] ?? null),
                    'price' => $this->integerOrNull($course['curso_valor'] ?? null),
                    'enrollment_fee' => $this->integerOrNull($course['curso_matricula'] ?? null),
                    'requirements' => $this->stringValue($course['curso_requisitos'] ?? ''),
                    'objectives' => $this->stringValue($course['curso_objetivo'] ?? ''),
                    'history' => $this->stringValue($course['curso_historia'] ?? ''),
                    'instructors' => $instructorIds,
                    'registration_url' => $this->stringValue($supplement['google_forms_link'] ?? ''),
                    'contact_email' => $this->stringValue($supplement['contact_email'] ?? ''),
                    'video_url' => $this->stringValue($supplement['youtube_video_link'] ?? ''),
                ],
                $runId,
                $this->assetFile('sn_cursos', $courseId, $supplement['image_cover'] ?? null, 'curso-' . $courseId, $runId)
            );
            foreach ($instructorIds as $instructor) {
                $teacherEntryId = (string) $instructor['entry_id'];
                $teacherSourceId = $this->teacherSourceIdForTarget($teacherEntryId, $teachers);
                if ($teacherSourceId !== null) {
                    $this->map($runId, 'sn_profesor', $teacherSourceId . ':course:' . $courseId, LegacyMigrationCatalog::TARGET_CMS, 'entry_reference', $teacherEntryId, LegacyMigrationCatalog::MAP_MAPPED, 'course instructor relation');
                }
            }
            if (isset($supplements[$courseId])) {
                $this->map($runId, 'sn_cursos', $courseId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $entryId, LegacyMigrationCatalog::MAP_SUPPLEMENTAL, 'supplemental course source');
                if ($this->stringValue($supplement['google_forms_link'] ?? '') !== '') {
                    $this->map($runId, 'sn_cursos', $courseId . ':google-form', LegacyMigrationCatalog::TARGET_CMS, 'external_link', (string) $entryId, LegacyMigrationCatalog::MAP_MAPPED, 'registration URL kept in curso_ficha');
                }
                if ($this->stringValue($supplement['youtube_video_link'] ?? '') !== '') {
                    $this->map($runId, 'sn_cursos', $courseId . ':youtube', LegacyMigrationCatalog::TARGET_CMS, 'video_reference', (string) $entryId, LegacyMigrationCatalog::MAP_MAPPED, 'video URL kept in curso_ficha');
                }
                if ($this->stringValue($supplement['pdf_file'] ?? '') !== '') {
                    $fileId = $this->assetFile('sn_cursos', $courseId . ':pdf', $supplement['pdf_file'], 'curso-' . $courseId . '.pdf', $runId);
                    if ($fileId !== null) {
                        $this->createDocumentBlock('sn_cursos', $courseId . ':document', (int) $entryId, $fileId, $this->stringValue($supplement['title'] ?? 'Documento del curso'), $runId);
                    }
                }
            }

            $images = array_values(array_filter(
                $this->visibleRows($tables['sn_escuela_img'] ?? [], 'escuela_img_display'),
                fn (array $image): bool => $this->stringValue($image['curso_id'] ?? '') === $courseId
            ));
            $this->applyGallery('sn_escuela', $courseId, (int) $entryId, $images, $runId, 'escuela_img_url', 'escuela_img_id', 'escuela_img_alt');
        }
    }

    /** @param array<string, mixed> $wizardExtra */
    private function applyCmsEntry(
        string $legacyTable,
        string $legacyId,
        string $collectionKey,
        string $slug,
        string $title,
        string $excerpt,
        array $wizardExtra,
        int $runId,
        ?int $featuredFileId = null
    ): int {
        $mapped = $this->repository->findMap($legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry');
        if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
            $this->summary['reused']['cms_entries']++;
            return (int) $mapped['target_id'];
        }

        $collectionId = $this->collections[$collectionKey] ?? throw new \RuntimeException("CMS collection '{$collectionKey}' is not configured.");
        $slug = $this->slug($slug);
        $existing = $this->findCmsEntry($collectionId, $slug);
        if ($existing !== null) {
            $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $existing, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic collection/slug lookup');
            $this->summary['reused']['cms_entries']++;
            return $existing;
        }

        $translation = [
            'language_id' => $this->spanishLanguageId(),
            'slug' => $slug,
            'title' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $slug)),
            'excerpt' => $excerpt,
        ];
        if ($featuredFileId !== null) {
            $translation['featured_image'] = ['source_kind' => 'file', 'file_id' => $featuredFileId];
        }
        $response = $this->cms->post('/cms/entries', [
            'collection_id' => $collectionId,
            'author_id' => null,
            'workflow_status' => 'draft',
            'published_at' => null,
            'scheduled_at' => null,
            'is_featured' => false,
            'view_count' => 0,
            'sort_order' => 0,
            'is_in_sitemap' => true,
            'translations' => [$translation],
            'wizard_extra' => $wizardExtra,
        ]);
        $id = $this->extractId($response);
        $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $id, LegacyMigrationCatalog::MAP_MAPPED, 'created through cms-domain API');
        $this->summary['created']['cms_entries']++;

        return $id;
    }

    /** @param array<string, mixed> $work */
    private function applyEvent(array $work, string $workKey, int $entryId, int $runId): int
    {
        $legacyId = $this->stringValue($work['id_obra'] ?? '');
        $mapped = $this->repository->findMap('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'event');
        if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
            $eventId = (int) $mapped['target_id'];
            $this->summary['reused']['events']++;
            $this->ensureEventReference($eventId, $entryId, $legacyId, $this->currentRunId);
            return $eventId;
        }

        $uuid = $this->uuidFromSeed('legacy:function:' . $workKey);
        $existing = $this->findEvent($uuid);
        if ($existing !== null) {
            $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'event', (string) $existing, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic UUID lookup');
            $this->summary['reused']['events']++;
            $this->ensureEventReference($existing, $entryId, $legacyId, $runId);
            return $existing;
        }

        $start = $this->dateTime($work['fecha_obra'] ?? null, $work['hora_obra'] ?? null);
        $description = $this->stringValue($work['descripcion_larga_obra'] ?? $work['descripcion_corta_obra'] ?? '');
        if ($description === '') {
            $description = $this->stringValue($work['titulo_obra'] ?? 'Función');
        }
        $response = $this->event->post('/events/events', [
            'uuid' => $uuid,
            'title' => $this->stringValue($work['titulo_obra'] ?? 'Función'),
            'event_type' => 'function',
            'description' => $description,
            'start_time' => $start,
            'end_time' => $this->plusHours($start, 2),
            'venue' => $this->stringValue($work['direccion_obra'] ?? ''),
            'capacity' => null,
            'available_spots' => null,
            'status' => 'scheduled',
        ]);
        $id = $this->extractId($response);
        $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'event', (string) $id, LegacyMigrationCatalog::MAP_MAPPED, 'created through event-domain API');
        $this->ensureEventReference($id, $entryId, $legacyId, $runId);
        $this->summary['created']['events']++;

        return $id;
    }

    private function ensureEventReference(int $eventId, int $entryId, string $legacyId, int $runId): void
    {
        $referenceKey = $legacyId . ':reference:' . $entryId;
        $mapped = $this->repository->findMap('sn_obra', $referenceKey, LegacyMigrationCatalog::TARGET_EVENT, 'event_reference');
        if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
            $this->summary['reused']['references']++;
            return;
        }

        $response = $this->event->post('/events/event-references', [
            'event_id' => $eventId,
            'source_system' => LegacyMigrationCatalog::TARGET_CMS,
            'source_type' => 'entry',
            'source_id' => (string) $entryId,
            'relation' => 'editorial_work',
            'metadata' => ['legacy_table' => 'sn_obra', 'legacy_id' => $legacyId],
        ]);
        $referenceId = $this->extractId($response);
        $this->map($runId, 'sn_obra', $referenceKey, LegacyMigrationCatalog::TARGET_EVENT, 'event_reference', (string) $referenceId, LegacyMigrationCatalog::MAP_MAPPED, 'idempotent CMS/Event reference');
        $this->summary['created']['references']++;
    }

    /** @param array<string, mixed> $work */
    private function applyOccurrence(array $work, int $eventId, string $legacyId, int $runId): void
    {
        $mapped = $this->repository->findMap('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence');
        if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
            $this->summary['reused']['occurrences']++;
            return;
        }
        $start = $this->dateTime($work['fecha_obra'] ?? null, $work['hora_obra'] ?? null);
        if ($start === null) {
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, 'sn_obra', $legacyId, 'invalid_date', null, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', null, 'fecha_obra', $work['fecha_obra'] ?? null, null, 'Occurrence was not created because the legacy date is invalid or empty.', 'warning');
            $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', null, LegacyMigrationCatalog::MAP_QUARANTINED, 'invalid legacy date');
            return;
        }
        $response = $this->event->post('/events/occurrences', [
            'event_id' => $eventId,
            'venue_id' => null,
            'start_time' => $start,
            'end_time' => $this->plusHours($start, 2) ?? $start,
            'status' => 'scheduled',
            'capacity' => 0,
            'available_spots' => 0,
        ]);
        $id = $this->extractId($response);
        $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', (string) $id, LegacyMigrationCatalog::MAP_MAPPED, 'created through event-domain API');
        $this->summary['created']['occurrences']++;
    }

    /** @param list<array<string, mixed>> $images */
    private function applyGallery(string $legacyTable, string $legacyId, int $entryId, array $images, int $runId, string $pathField, string $idField, string $altField): void
    {
        if ($images === []) {
            return;
        }
        $parentMap = $this->repository->findMap($legacyTable, $legacyId . ':gallery', LegacyMigrationCatalog::TARGET_CMS, 'gallery');
        $parentId = $this->positiveId($parentMap['target_id'] ?? null);
        if ($parentId === null) {
            $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
                'block_id' => $this->blockTypes['gallery'] ?? throw new \RuntimeException('CMS block type gallery is not configured.'),
                'owner_type' => 'entry',
                'owner_id' => $entryId,
                'parent_instance_id' => null,
                'sort_order' => 100,
                'column_index' => null,
                'is_active' => true,
                'block_config' => ['presentation_mode' => 'modal_preview', 'columns' => '3', 'gap' => 'medium'],
                'translations' => [],
            ]);
            $parentId = $this->extractId($response);
            $this->map($runId, $legacyTable, $legacyId . ':gallery', LegacyMigrationCatalog::TARGET_CMS, 'gallery', (string) $parentId, LegacyMigrationCatalog::MAP_MAPPED, 'gallery container');
            $this->summary['created']['blocks']++;
        } else {
            $this->summary['reused']['blocks']++;
        }

        foreach ($images as $image) {
            $imageId = $this->stringValue($image[$idField] ?? '');
            if ($imageId === '') {
                continue;
            }
            $mapped = $this->repository->findMap($this->imageTable($legacyTable), $imageId, LegacyMigrationCatalog::TARGET_CMS, 'gallery_item');
            if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
                $this->summary['reused']['blocks']++;
                continue;
            }
            $fileId = $this->assetFile($this->imageTable($legacyTable), $imageId, $image[$pathField] ?? null, 'gallery-' . $imageId, $runId);
            if ($fileId === null) {
                continue;
            }
            $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
                'block_id' => $this->blockTypes['gallery_item'] ?? throw new \RuntimeException('CMS block type gallery_item is not configured.'),
                'owner_type' => 'entry',
                'owner_id' => $entryId,
                'parent_instance_id' => $parentId,
                'sort_order' => (int) ($image['escuela_img_posicion'] ?? $image['id_slider'] ?? $imageId),
                'column_index' => null,
                'is_active' => true,
                'block_config' => ['image' => ['source_kind' => 'file', 'file_id' => $fileId]],
                'translations' => [[
                    'language_id' => $this->spanishLanguageId(),
                    'block_data' => ['alt' => $this->stringValue($image[$altField] ?? ''), 'caption' => $this->stringValue($image[$altField] ?? '')],
                    'is_published' => true,
                ]],
            ]);
            $blockId = $this->extractId($response);
            $this->map($runId, $this->imageTable($legacyTable), $imageId, LegacyMigrationCatalog::TARGET_CMS, 'gallery_item', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'gallery item block');
            $this->summary['created']['blocks']++;
        }
    }

    private function createDocumentBlock(string $legacyTable, string $legacyId, int $entryId, int $fileId, string $title, int $runId): void
    {
        $mapped = $this->repository->findMap($legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'document_block');
        if ($mapped !== null && $this->positiveId($mapped['target_id'] ?? null) !== null) {
            $this->summary['reused']['blocks']++;
            return;
        }
        $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
            'block_id' => $this->blockTypes['document_download'] ?? throw new \RuntimeException('CMS block type document_download is not configured.'),
            'owner_type' => 'entry',
            'owner_id' => $entryId,
            'parent_instance_id' => null,
            'sort_order' => 200,
            'column_index' => null,
            'is_active' => true,
            'block_config' => ['document' => ['source_kind' => 'file', 'file_id' => $fileId], 'open_in_new_tab' => true],
            'translations' => [[
                'language_id' => $this->spanishLanguageId(),
                'block_data' => ['title' => $title, 'description' => '', 'button_label' => 'Descargar'],
                'is_published' => true,
            ]],
        ]);
        $blockId = $this->extractId($response);
        $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'document_block', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'document download block');
        $this->summary['created']['blocks']++;
    }

    private function assetFile(string $legacyTable, string $legacyId, mixed $legacyPath, string $filename, int $runId): ?int
    {
        $path = $this->stringValue($legacyPath);
        if ($path === '' || $this->assetResolver === null) {
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, $legacyTable, $legacyId, 'asset_missing', null, LegacyMigrationCatalog::TARGET_HUB, 'file', null, 'asset_path', $path, null, 'Asset root is not configured for apply.', 'warning');
            return null;
        }
        $map = $this->repository->findMap($legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_HUB, 'file');
        if ($map !== null && $this->positiveId($map['target_id'] ?? null) !== null) {
            $this->summary['reused']['files']++;
            return (int) $map['target_id'];
        }
        $asset = $this->assetResolver->resolve($path);
        if (($asset['status'] ?? null) !== 'resolved' || ! is_string($asset['absolute_path'] ?? null)) {
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, $legacyTable, $legacyId, 'asset_missing', null, LegacyMigrationCatalog::TARGET_HUB, 'file', null, 'asset_path', $path, null, (string) ($asset['reason'] ?? 'Asset could not be resolved.'), 'warning');
            return null;
        }
        $extension = pathinfo((string) $asset['absolute_path'], PATHINFO_EXTENSION);
        $uploadName = $filename . ($extension !== '' ? '.' . strtolower($extension) : '');
        $response = $this->hub->upload('/files/upload', (string) $asset['absolute_path'], $uploadName, ['filename' => $uploadName, 'visibility' => 'public']);
        $id = $this->extractId($response);
        $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_HUB, 'file', (string) $id, LegacyMigrationCatalog::MAP_MAPPED, 'uploaded through hub file API sha256=' . (string) ($asset['sha256'] ?? ''));
        $this->summary['created']['files']++;

        return $id;
    }

    private function loadCmsCatalog(): void
    {
        $this->collections = $this->indexByKey($this->list($this->cms->get('/cms/collections', ['per_page' => 500])), ['collection_key', 'key', 'slug']);
        $this->languages = $this->indexLanguages($this->list($this->cms->get('/cms/languages', ['per_page' => 100])));
        $this->blockTypes = $this->indexByKey($this->list($this->cms->get('/cms/block-types', ['per_page' => 100])), ['block_key', 'key', 'slug']);
        if (! isset($this->languages['es']) && $this->languages !== []) {
            $this->languages['es'] = (int) reset($this->languages);
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function list(array $response): array
    {
        $data = $response['data'] ?? $response;
        if (! is_array($data)) {
            return [];
        }
        foreach (['items', 'results', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }
        return array_is_list($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param list<string> $keys
     * @return array<string, int>
     */
    private function indexByKey(array $items, array $keys): array
    {
        $index = [];
        foreach ($items as $item) {
            $id = $this->positiveId($item['id'] ?? null);
            if ($id === null) {
                continue;
            }
            foreach ($keys as $key) {
                $value = $this->stringValue($item[$key] ?? '');
                if ($value !== '') {
                    $index[$value] = $id;
                }
            }
        }
        return $index;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function indexLanguages(array $items): array
    {
        $index = [];
        foreach ($items as $item) {
            $id = $this->positiveId($item['id'] ?? null);
            if ($id === null) {
                continue;
            }
            foreach (['code', 'locale', 'language_code', 'slug'] as $key) {
                $value = strtolower($this->stringValue($item[$key] ?? ''));
                if ($value !== '') {
                    $index[$value] = $id;
                    $index[substr($value, 0, 2)] = $id;
                }
            }
        }
        return $index;
    }

    private function findCmsEntry(int $collectionId, string $slug): ?int
    {
        foreach ($this->list($this->cms->get('/cms/entries', ['per_page' => 500])) as $item) {
            if ((int) ($item['collection_id'] ?? 0) !== $collectionId) {
                continue;
            }
            if ($this->stringValue($item['slug'] ?? '') === $slug) {
                return $this->positiveId($item['id'] ?? null);
            }
            foreach (($item['translations'] ?? []) as $translation) {
                if (is_array($translation) && $this->stringValue($translation['slug'] ?? '') === $slug) {
                    return $this->positiveId($item['id'] ?? null);
                }
            }
        }
        return null;
    }

    private function findEvent(string $uuid): ?int
    {
        foreach ($this->list($this->event->get('/events/events', ['per_page' => 100])) as $item) {
            if ($this->stringValue($item['uuid'] ?? '') === $uuid) {
                return $this->positiveId($item['id'] ?? null);
            }
        }
        return null;
    }

    private function map(int $runId, string $legacyTable, string $legacyId, string $targetSystem, string $targetType, ?string $targetId, string $status, string $note): void
    {
        $this->repository->upsertMap($runId, $legacyTable, $legacyId, $targetSystem, $targetType, $targetId, $this->sourceHash, $status, $status === LegacyMigrationCatalog::MAP_DUPLICATE, $note);
    }

    private function imageTable(string $ownerTable): string
    {
        return $ownerTable === 'sn_obra' ? 'sn_slider_cartelera' : 'sn_escuela_img';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function visibleRows(array $rows, string $field): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => ! array_key_exists($field, $row) || (int) $row[$field] === 1));
    }

    /** @param array<string, mixed> $row */
    private function workKey(array $row): string
    {
        return $this->slug($this->stringValue($row['url'] ?? $row['titulo_obra'] ?? 'obra'));
    }

    /** @param array<string, mixed> $row */
    private function courseKey(array $row): string
    {
        $slug = $this->stringValue($row['url'] ?? $row['curso_titulo'] ?? 'curso-' . ($row['curso_id'] ?? 'unknown'));
        $id = $this->stringValue($row['curso_id'] ?? '');
        return $this->slug($slug) . ($id !== '' ? '-' . $this->slug($id) : '');
    }

    /** @param array<string, mixed> $row */
    private function numericId(array $row, string $key): int
    {
        return (int) ($row[$key] ?? 0);
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower(is_string($ascii) ? $ascii : $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        return trim($value, '-') ?: 'sin-identidad';
    }

    private function positiveId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** @param array<string, mixed> $response */
    private function extractId(array $response): int
    {
        $data = $response['data'] ?? $response;
        if (is_array($data) && $this->positiveId($data['id'] ?? null) !== null) {
            return (int) $data['id'];
        }
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item) && $this->positiveId($item['id'] ?? null) !== null) {
                    return (int) $item['id'];
                }
            }
        }
        throw new \RuntimeException('Legacy apply received a successful response without a target ID.');
    }

    private function validDate(mixed $value): bool
    {
        $date = $this->stringValue($value);
        if ($date === '' || $date === '0000-00-00') {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function dateTime(mixed $date, mixed $time): ?string
    {
        if (! $this->validDate($date)) {
            return null;
        }
        $timeValue = $this->stringValue($time);
        if ($timeValue === '' || ! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $timeValue)) {
            $timeValue = '00:00:00';
        } elseif (strlen($timeValue) === 5) {
            $timeValue .= ':00';
        }
        return $this->stringValue($date) . ' ' . $timeValue;
    }

    private function plusHours(?string $dateTime, int $hours): ?string
    {
        if ($dateTime === null) {
            return null;
        }
        $parsed = new DateTimeImmutable($dateTime);
        return $parsed->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function timeRange(mixed $start, mixed $end): string
    {
        $start = $this->stringValue($start);
        $end = $this->stringValue($end);
        return $start === '' && $end === '' ? '' : trim($start . ' - ' . $end, ' -');
    }

    private function youtubeId(string $value): string
    {
        if (preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{6,})/', $value, $matches)) {
            return $matches[1];
        }
        return $value;
    }

    private function youtubeUrl(string $value): string
    {
        return str_starts_with($value, 'http') ? $value : 'https://www.youtube.com/watch?v=' . $this->youtubeId($value);
    }

    /** @param array<string, int> $teachers */
    private function teacherSourceIdForTarget(string $targetId, array $teachers): ?string
    {
        foreach ($teachers as $sourceId => $mappedTargetId) {
            if ((string) $mappedTargetId === $targetId) {
                return $sourceId;
            }
        }
        return null;
    }

    private function spanishLanguageId(): int
    {
        $id = $this->languages['es'] ?? null;
        if ($id === null && $this->languages !== []) {
            $id = (int) reset($this->languages);
        }
        if (! is_int($id) || $id <= 0) {
            throw new \RuntimeException('CMS has no active language available for legacy content.');
        }
        return $id;
    }

    private function uuidFromSeed(string $seed): string
    {
        $hash = hash('sha256', $seed, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(substr($hash, 0, 16)), 4));
    }
}
