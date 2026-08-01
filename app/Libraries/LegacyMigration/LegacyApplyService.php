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
    /** @var array<string, list<array<string, mixed>>> */
    private array $cmsBlockInstances = [];
    /** @var list<array<string, mixed>>|null */
    private ?array $cmsEntries = null;

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
        $this->cmsBlockInstances = [];
        $this->cmsEntries = null;
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
        } elseif ($slice === 'C') {
            $this->applySliceC($tables, $runId);
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

        $audiences = [];
        foreach ($tables['sn_publico'] ?? [] as $audience) {
            $audienceId = $this->stringValue($audience['id_publico'] ?? '');
            $audienceName = $this->stringValue($audience['nombre_publico'] ?? '');
            if ($audienceId !== '' && $audienceName !== '') {
                $audiences[$audienceId] = $audienceName;
            }
        }

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
                [
                    'name' => $this->stringValue($company['nombre_compania'] ?? ''),
                    'summary' => $this->stringValue($company['resena_compania'] ?? ''),
                    'description' => $this->stringValue($company['resena_compania'] ?? ''),
                    'director' => $this->stringValue($company['director_compania'] ?? ''),
                ],
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
                    'performance_date' => $this->validDate($canonical['fecha_obra'] ?? null) ? $this->stringValue($canonical['fecha_obra']) : null,
                    'performance_time' => $this->stringValue($canonical['hora_obra'] ?? ''),
                    'venue' => $this->stringValue($canonical['direccion_obra'] ?? ''),
                    'price_regular' => $this->stringValue($canonical['valor1_obra'] ?? ''),
                    'price_discount' => $this->stringValue($canonical['valor2_obra'] ?? ''),
                    'audience' => $audiences[$this->stringValue($canonical['id_publico'] ?? '')] ?? '',
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
                [
                    'provider' => 'youtube',
                    'video_id' => $this->youtubeId($videoKey),
                    'video_url' => $this->youtubeUrl($videoKey),
                    'recorded_at' => $this->validDate($video['fecha'] ?? null) ? $this->stringValue($video['fecha']) : null,
                ],
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

        $globalExisting = $this->findCmsEntry(null, $slug);
        if ($globalExisting !== null) {
            $slug = $slug . '-' . $collectionKey;
            $existingNew = $this->findCmsEntry($collectionId, $slug);
            if ($existingNew !== null) {
                $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', (string) $existingNew, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic collection/slug lookup after resolution');
                $this->summary['reused']['cms_entries']++;
                return $existingNew;
            }
        }

        $translations = [];
        $cachedTranslations = [];
        foreach ($this->languages as $code => $langId) {
            $trans = [
                'language_id' => $langId,
                'slug' => $slug,
                'title' => $title !== '' ? $title : ucfirst(str_replace('-', ' ', $slug)),
                'excerpt' => mb_strimwidth(trim($excerpt), 0, 497, '...'),
            ];
            if ($featuredFileId !== null) {
                $trans['featured_image'] = ['source_kind' => 'file', 'file_id' => $featuredFileId];
            }
            $translations[] = $trans;
            $cachedTranslations[] = [
                'language_id' => $langId,
                'slug' => $slug,
                'title' => $title,
            ];
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
            'translations' => $translations,
            'wizard_extra' => $wizardExtra,
        ]);
        $id = $this->extractId($response);
        if ($this->cmsEntries !== null) {
            $this->cmsEntries[] = [
                'id' => $id,
                'collection_id' => $collectionId,
                'slug' => $slug,
                'translations' => $cachedTranslations,
            ];
        }
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
        if ($mapped !== null && ($mapped['status'] ?? null) === LegacyMigrationCatalog::MAP_QUARANTINED && (string) ($mapped['source_hash'] ?? '') === $this->sourceHash) {
            $this->summary['issues']++;
            return;
        }
        $start = $this->dateTime($work['fecha_obra'] ?? null, $work['hora_obra'] ?? null);
        if ($start === null) {
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, 'sn_obra', $legacyId, 'invalid_date', null, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', null, 'fecha_obra', $work['fecha_obra'] ?? null, null, 'Occurrence was not created because the legacy date is invalid or empty.', 'warning');
            $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', null, LegacyMigrationCatalog::MAP_QUARANTINED, 'invalid legacy date');
            return;
        }
        $end = $this->plusHours($start, 2) ?? $start;
        $existing = $this->findOccurrence($eventId, $start, $end);
        if ($existing !== null) {
            $this->map($runId, 'sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', (string) $existing, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic event/time lookup');
            $this->summary['reused']['occurrences']++;
            return;
        }
        $response = $this->event->post('/events/occurrences', [
            'event_id' => $eventId,
            'venue_id' => null,
            'start_time' => $start,
            'end_time' => $end,
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
            $galleryBlockId = $this->blockTypes['gallery'] ?? throw new \RuntimeException('CMS block type gallery is not configured.');
            $parentId = $this->findCmsBlock($entryId, $galleryBlockId, null, 100, null);
            if ($parentId !== null) {
                $this->map($runId, $legacyTable, $legacyId . ':gallery', LegacyMigrationCatalog::TARGET_CMS, 'gallery', (string) $parentId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic gallery lookup');
                $this->summary['reused']['blocks']++;
            } else {
                $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
                'block_id' => $galleryBlockId,
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
                $this->rememberCmsBlock($parentId, $galleryBlockId, $entryId, null, 100, null);
                $this->map($runId, $legacyTable, $legacyId . ':gallery', LegacyMigrationCatalog::TARGET_CMS, 'gallery', (string) $parentId, LegacyMigrationCatalog::MAP_MAPPED, 'gallery container');
                $this->summary['created']['blocks']++;
            }
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
            $galleryItemBlockId = $this->blockTypes['gallery_item'] ?? throw new \RuntimeException('CMS block type gallery_item is not configured.');
            $sortOrder = (int) ($image['escuela_img_posicion'] ?? $image['id_slider'] ?? $imageId);
            $recoveredBlockId = $this->findCmsBlock($entryId, $galleryItemBlockId, $parentId, $sortOrder, $fileId);
            if ($recoveredBlockId !== null) {
                $this->map($runId, $this->imageTable($legacyTable), $imageId, LegacyMigrationCatalog::TARGET_CMS, 'gallery_item', (string) $recoveredBlockId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic gallery item lookup');
                $this->summary['reused']['blocks']++;
                continue;
            }
            $translations = [];
            foreach ($this->languages as $code => $langId) {
                $translations[] = [
                    'language_id' => $langId,
                    'block_data' => ['alt' => $this->stringValue($image[$altField] ?? ''), 'caption' => $this->stringValue($image[$altField] ?? '')],
                    'is_published' => true,
                ];
            }
            $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
                'block_id' => $galleryItemBlockId,
                'owner_type' => 'entry',
                'owner_id' => $entryId,
                'parent_instance_id' => $parentId,
                'sort_order' => $sortOrder,
                'column_index' => null,
                'is_active' => true,
                'block_config' => ['image' => ['source_kind' => 'file', 'file_id' => $fileId]],
                'translations' => $translations,
            ]);
            $blockId = $this->extractId($response);
            $this->rememberCmsBlock($blockId, $galleryItemBlockId, $entryId, $parentId, $sortOrder, $fileId);
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
        $documentBlockId = $this->blockTypes['document_download'] ?? throw new \RuntimeException('CMS block type document_download is not configured.');
        $recoveredBlockId = $this->findCmsBlock($entryId, $documentBlockId, null, 200, $fileId);
        if ($recoveredBlockId !== null) {
            $this->map($runId, $legacyTable, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'document_block', (string) $recoveredBlockId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic document block lookup');
            $this->summary['reused']['blocks']++;
            return;
        }
        $translations = [];
        foreach ($this->languages as $code => $langId) {
            $translations[] = [
                'language_id' => $langId,
                'block_data' => ['title' => $title, 'description' => '', 'button_label' => 'Descargar'],
                'is_published' => true,
            ];
        }
        $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
            'block_id' => $documentBlockId,
            'owner_type' => 'entry',
            'owner_id' => $entryId,
            'parent_instance_id' => null,
            'sort_order' => 200,
            'column_index' => null,
            'is_active' => true,
            'block_config' => ['document' => ['source_kind' => 'file', 'file_id' => $fileId], 'open_in_new_tab' => true],
            'translations' => $translations,
        ]);
        $blockId = $this->extractId($response);
        $this->rememberCmsBlock($blockId, $documentBlockId, $entryId, null, 200, $fileId);
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

        try {
            $response = $this->hub->upload('/files/upload', (string) $asset['absolute_path'], $uploadName, ['filename' => $uploadName, 'visibility' => 'public']);
        } catch (\RuntimeException $exception) {
            // A single asset the Hub rejects (oversized, unsupported mime type, etc.) must not
            // abort the whole slice — record it and let the rest of the run proceed, same as an
            // unresolved asset_missing path. Never lose the row silently.
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, $legacyTable, $legacyId, 'asset_rejected', null, LegacyMigrationCatalog::TARGET_HUB, 'file', null, 'asset_path', $path, null, $exception->getMessage(), 'warning');

            return null;
        }

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
     * @return list<array<string, mixed>>
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

    private function findCmsEntry(?int $collectionId, string $slug): ?int
    {
        if ($this->cmsEntries === null) {
            $this->cmsEntries = $this->list($this->cms->get('/cms/entries', ['per_page' => 1000]));
        }

        foreach ($this->cmsEntries as $item) {
            if ($collectionId !== null && (int) ($item['collection_id'] ?? 0) !== $collectionId) {
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

    private function findOccurrence(int $eventId, string $start, string $end): ?int
    {
        foreach ($this->list($this->event->get('/events/occurrences', ['per_page' => 100])) as $item) {
            if ((int) ($item['event_id'] ?? 0) !== $eventId) {
                continue;
            }
            if ($this->stringValue($item['start_time'] ?? '') !== $start || $this->stringValue($item['end_time'] ?? '') !== $end) {
                continue;
            }
            $id = $this->positiveId($item['id'] ?? null);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function findCmsBlock(int $entryId, int $blockId, ?int $parentId, int $sortOrder, ?int $fileId, string $ownerType = 'entry'): ?int
    {
        $cacheKey = $ownerType . ':' . $entryId;
        if (! isset($this->cmsBlockInstances[$cacheKey])) {
            $path = $ownerType === 'entry' ? '/cms/entries/' . $entryId . '/blocks' : '/cms/pages/' . $entryId . '/blocks';
            $this->cmsBlockInstances[$cacheKey] = $this->list($this->cms->get($path, ['per_page' => 1000]));
        }

        foreach ($this->cmsBlockInstances[$cacheKey] as $item) {
            if ((int) ($item['block_id'] ?? 0) !== $blockId || (string) ($item['owner_type'] ?? '') !== $ownerType || (int) ($item['owner_id'] ?? 0) !== $entryId || (int) ($item['sort_order'] ?? 0) !== $sortOrder) {
                continue;
            }
            $candidateParentId = $this->positiveId($item['parent_instance_id'] ?? null);
            if ($candidateParentId !== $parentId) {
                continue;
            }
            if ($fileId !== null) {
                $config = $item['block_config'] ?? [];
                if (is_string($config)) {
                    $decoded = json_decode($config, true);
                    $config = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($config)) {
                    continue;
                }
                $candidateFileId = $config['image']['file_id'] ?? $config['document']['file_id'] ?? null;
                if ((int) $candidateFileId !== $fileId) {
                    continue;
                }
            }
            $id = $this->positiveId($item['id'] ?? null);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function rememberCmsBlock(int $instanceId, int $blockId, int $entryId, ?int $parentId, int $sortOrder, ?int $fileId, string $ownerType = 'entry'): void
    {
        $cacheKey = $ownerType . ':' . $entryId;
        if (! isset($this->cmsBlockInstances[$cacheKey])) {
            return;
        }
        $config = [];
        if ($fileId !== null) {
            $config = ['image' => ['file_id' => $fileId]];
        }
        $this->cmsBlockInstances[$cacheKey][] = [
            'id' => $instanceId,
            'block_id' => $blockId,
            'owner_type' => $ownerType,
            'owner_id' => $entryId,
            'parent_instance_id' => $parentId,
            'sort_order' => $sortOrder,
            'block_config' => $config,
        ];
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
                return (string) $sourceId;
            }
        }
        return null;
    }

    private function uuidFromSeed(string $seed): string
    {
        $hash = hash('sha256', $seed, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(substr($hash, 0, 16)), 4));
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applySliceC(array $tables, int $runId): void
    {
        // 1. Exposiciones
        $this->applyExposiciones($tables, $runId);

        // 2. Noticias
        $this->applyNoticias($tables, $runId);

        // 3. Publicaciones
        $this->applyPublicaciones($tables, $runId);

        // 4. Festivales
        $this->applyFestivales($tables, $runId);

        // 5. Funcionarios
        $this->applyFuncionarios($tables, $runId);

        // 6. Museo
        $this->applyMuseo($tables, $runId);

        // 7. Home page hero slides
        $this->applyHomeSliderSlides($tables, $runId);
    }

    /**
     * sn_slider (499 rows, categoria 1-5 mapping to sn_categoria_slider: Index,
     * Quienes Somos, Historia, Upa Chalupa, Anímate) only migrates categoria=1
     * (home/"Index") here: the home page already ships a seeded `hero_slider`
     * container to append `slide_banner` children to. nosotros/historia use
     * hero_banner/cards_slider instead — they have no slider container — and
     * Upa Chalupa/Anímate have no dedicated page at all yet. Adding a slider
     * container to a page that doesn't have one, or standing up new pages for
     * TeatroEscuela/Anímate, is a page-layout/IA decision, not a data
     * transformation this command should make unilaterally.
     *
     * @param array<string, list<array<string, mixed>>> $tables
     */
    private function applyHomeSliderSlides(array $tables, int $runId): void
    {
        $homePageId = 12;

        $slides = array_values(array_filter(
            $tables['sn_slider'] ?? [],
            static fn (array $row): bool => (int) ($row['display'] ?? 0) === 1 && (int) ($row['categoria'] ?? 0) === 1
        ));
        if ($slides === []) {
            return;
        }

        $slideBannerBlockId = $this->blockTypes['slide_banner'] ?? throw new \RuntimeException('CMS block type slide_banner is not configured.');
        $heroSliderId = $this->findHeroSliderInstanceId($homePageId);
        if ($heroSliderId === null) {
            $this->summary['issues']++;
            $this->repository->recordIssue($this->currentRunId, 'sn_slider', 'hero_slider', 'target_missing', null, LegacyMigrationCatalog::TARGET_CMS, 'page_block', null, 'parent_block', null, null, 'Home page has no hero_slider container to attach slides to.', 'warning');

            return;
        }

        $sortOrder = 100;
        foreach ($slides as $slide) {
            $id = $this->stringValue($slide['id'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }

            $sortOrder++;
            $recoveredId = $this->findCmsBlock($homePageId, $slideBannerBlockId, $heroSliderId, $sortOrder, null, 'page');
            if ($recoveredId !== null) {
                $this->map($runId, 'sn_slider', $id, LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $recoveredId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic slide lookup');
                $this->summary['reused']['blocks']++;
                continue;
            }

            $imagePath = $this->stringValue($slide['archivo'] ?? '');
            $fileId = $imagePath !== '' ? $this->assetFile('sn_slider', $id, $imagePath, 'slide-' . $id, $runId) : null;
            $heading = $this->stringValue($slide['texto'] ?? '');
            $link = $this->stringValue($slide['link'] ?? '');

            $translations = [];
            foreach ($this->languages as $code => $langId) {
                $translations[] = [
                    'language_id' => $langId,
                    'block_data' => [
                        'heading' => $heading !== '' ? $heading : 'Teatromuseo',
                        'subtitle' => null,
                        'cta_url' => $link !== '' ? $link : null,
                        'cta_label' => $link !== '' ? 'Ver más' : null,
                    ],
                    'is_published' => true,
                ];
            }
            $blockConfig = ['image' => $fileId !== null ? ['source_kind' => 'file', 'file_id' => $fileId] : null];
            $response = $this->cms->post('/cms/pages/' . $homePageId . '/blocks', [
                'block_id' => $slideBannerBlockId,
                'owner_type' => 'page',
                'owner_id' => $homePageId,
                'parent_instance_id' => $heroSliderId,
                'sort_order' => $sortOrder,
                'column_index' => null,
                'is_active' => true,
                'block_config' => $blockConfig,
                'translations' => $translations,
            ]);
            $blockId = $this->extractId($response);
            $this->rememberCmsBlock($blockId, $slideBannerBlockId, $homePageId, $heroSliderId, $sortOrder, $fileId, 'page');
            $this->map($runId, 'sn_slider', $id, LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'home hero slide');
            $this->summary['created']['blocks']++;
        }
    }

    private function findHeroSliderInstanceId(int $pageId): ?int
    {
        $heroSliderBlockId = $this->blockTypes['hero_slider'] ?? null;
        if ($heroSliderBlockId === null) {
            return null;
        }

        $blocks = $this->list($this->cms->get('/cms/pages/' . $pageId . '/blocks', ['per_page' => 200]));
        foreach ($blocks as $block) {
            if ((int) ($block['block_id'] ?? 0) === $heroSliderBlockId && (int) ($block['parent_instance_id'] ?? -1) === 0) {
                return (int) ($block['id'] ?? 0);
            }
        }

        return null;
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyExposiciones(array $tables, int $runId): void
    {
        $expoRows = $this->visibleRows($tables['sn_expo'] ?? [], 'display');
        usort($expoRows, fn (array $left, array $right): int => $this->numericId($left, 'id') <=> $this->numericId($right, 'id'));
        $expoRows = array_slice($expoRows, 0, 10);

        $allExpoImages = $tables['sn_expo_img'] ?? [];
        $imagesByExpo = [];
        foreach ($allExpoImages as $img) {
            $eid = $this->stringValue($img['expo_id'] ?? '');
            if ($eid !== '' && ($this->numericId($img, 'display') === 1)) {
                $imagesByExpo[$eid][] = $img;
            }
        }

        foreach ($expoRows as $expo) {
            $id = $this->stringValue($expo['id'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }
            $slug = $this->slug((string) ($expo['url'] ?: $expo['titulo'] ?: 'expo-' . $id));

            $expoImages = $imagesByExpo[$id] ?? [];
            $coverFileId = null;
            if ($expoImages !== []) {
                $coverImg = $expoImages[0];
                $coverPath = $this->stringValue($coverImg['img'] ?? '');
                $coverId = $this->stringValue($coverImg['id'] ?? '');
                if ($coverPath !== '') {
                    $coverFileId = $this->assetFile('sn_expo_img', $coverId, $coverPath, 'expo-cover-' . $coverId, $runId);
                }
            }

            $wizardExtra = [
                'author_text' => $this->stringValue($expo['autor'] ?? ''),
                'opening_date' => $this->validDate($expo['fecha_desde'] ?? null) ? $this->stringValue($expo['fecha_desde']) : null,
                'closing_date' => $this->validDate($expo['fecha_hasta'] ?? null) ? $this->stringValue($expo['fecha_hasta']) : null,
                'description' => $this->stringValue($expo['descripcion'] ?? ''),
            ];

            $entryId = $this->applyCmsEntry(
                'sn_expo',
                $id,
                'exposiciones',
                $slug,
                $this->stringValue($expo['titulo'] ?? 'Exposición'),
                $this->stringValue($expo['descripcion'] ?? ''),
                $wizardExtra,
                $runId,
                $coverFileId
            );

            if (count($expoImages) > 1) {
                $galleryImages = array_slice($expoImages, 1);
                $this->applyGallery('sn_expo', $id, (int) $entryId, $galleryImages, $runId, 'img', 'id', 'id');
            }
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyNoticias(array $tables, int $runId): void
    {
        $newsRows = $this->visibleRows($tables['sn_noticias'] ?? [], 'disp_noticias');
        usort($newsRows, fn (array $left, array $right): int => $this->numericId($left, 'id_noticias') <=> $this->numericId($right, 'id_noticias'));
        $newsRows = array_slice($newsRows, 0, 20);

        foreach ($newsRows as $news) {
            $id = $this->stringValue($news['id_noticias'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }
            $slug = $this->slug((string) ($news['url'] ?: $news['titulo'] ?: 'noticia-' . $id));

            $coverFileId = null;
            $coverPath = $this->stringValue($news['foto'] ?? '');
            if ($coverPath !== '') {
                $coverFileId = $this->assetFile('sn_noticias', $id, $coverPath, 'news-cover-' . $id, $runId);
            }

            // The 'news' collection's block_template declares a single required,
            // auto_create rich_text block ("Titular") at sort_order 1 — feed its
            // 'cuerpo' through wizard_extra so EntryBlockTemplateInitializer fills
            // that exact block, instead of leaving it required-but-empty and
            // creating a second, redundant rich_text block by hand (the shape this
            // code used before the JsonCastNormalizer fix made wizard_extra
            // matching actually work — see LEGACY-MAP-015).
            $wizardExtra = [
                'publish_date' => $this->validDate($news['fecha'] ?? null) ? $this->stringValue($news['fecha']) : null,
                'lead' => $this->stringValue($news['lead'] ?? ''),
                'content' => $this->stringValue($news['cuerpo'] ?? ''),
            ];

            $this->applyCmsEntry(
                'sn_noticias',
                $id,
                'noticias',
                $slug,
                $this->stringValue($news['titulo'] ?? 'Noticia'),
                $this->stringValue($news['lead'] ?? ''),
                $wizardExtra,
                $runId,
                $coverFileId
            );
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyPublicaciones(array $tables, int $runId): void
    {
        $pubSources = [
            'sn_editorial' => 'editorial',
            'sn_prensa' => 'press',
            'sn_administracion' => 'transparency',
        ];
        $pubLimit = 30;
        $pubCount = 0;

        foreach ($pubSources as $table => $type) {
            if ($pubCount >= $pubLimit) {
                break;
            }
            $pubRows = $this->visibleRows($tables[$table] ?? [], 'display');
            usort($pubRows, fn (array $left, array $right): int => $this->numericId($left, 'id') <=> $this->numericId($right, 'id'));
            $selectedPubs = array_slice($pubRows, 0, max(0, $pubLimit - $pubCount));
            $pubCount += count($selectedPubs);

            foreach ($selectedPubs as $pub) {
                $id = $this->stringValue($pub['id'] ?? '');
                if ($id === '') {
                    $this->summary['issues']++;
                    continue;
                }
                $slug = $this->slug((string) (($pub['url'] ?? $pub['link'] ?? $pub['titulo'] ?? '') ?: $type . '-' . $id));

                $coverFileId = null;
                $coverPath = $this->stringValue($pub['foto'] ?? '');
                if ($coverPath !== '') {
                    $coverFileId = $this->assetFile($table, $id, $coverPath, 'pub-cover-' . $id, $runId);
                }

                $wizardExtra = [
                    'publication_type' => $type,
                    'publish_date' => $this->validDate($pub['fecha'] ?? null) ? $this->stringValue($pub['fecha']) : null,
                    'external_link' => $this->stringValue($pub['link'] ?? ''),
                ];

                $entryId = $this->applyCmsEntry(
                    $table,
                    $id,
                    'publicaciones',
                    $slug,
                    $this->stringValue($pub['titulo'] ?? 'Publicación'),
                    $this->stringValue($pub['descripcion'] ?? ''),
                    $wizardExtra,
                    $runId,
                    $coverFileId
                );

                $pdfPath = $this->stringValue($pub['archivo'] ?? '');
                if ($pdfPath !== '') {
                    $pdfFileId = $this->assetFile($table, $id . ':pdf', $pdfPath, 'pub-pdf-' . $id, $runId);
                    if ($pdfFileId !== null) {
                        $this->createDocumentBlock($table, $id . ':pdf', (int) $entryId, $pdfFileId, $this->stringValue($pub['titulo'] ?? 'Documento'), $runId);
                    }
                }
            }
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyFestivales(array $tables, int $runId): void
    {
        $upaRows = $tables['sn_upa'] ?? [];
        foreach ($upaRows as $upa) {
            $id = $this->stringValue($upa['id_upa'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }
            $slug = 'upa-chalupa-2019';

            $wizardExtra = [
                'subtitle' => $this->stringValue($upa['pie'] ?? ''),
            ];

            $entryId = $this->applyCmsEntry(
                'sn_upa',
                $id,
                'festivales',
                $slug,
                $this->stringValue($upa['titulo'] ?? 'Festival Upa Chalupa'),
                $this->stringValue($upa['pie'] ?? ''),
                $wizardExtra,
                $runId
            );

            $cuerpo = $this->stringValue($upa['cuerpo'] ?? '');
            if ($cuerpo !== '') {
                $richTextBlockId = $this->blockTypes['rich_text'] ?? throw new \RuntimeException('CMS block type rich_text is not configured.');
                $recoveredBlockId = $this->findCmsBlock($entryId, $richTextBlockId, null, 10, null);
                if ($recoveredBlockId !== null) {
                    $this->map($runId, 'sn_upa', $id . ':cuerpo', LegacyMigrationCatalog::TARGET_CMS, 'rich_text_block', (string) $recoveredBlockId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic body block lookup');
                    $this->summary['reused']['blocks']++;
                } else {
                    $translations = [];
                    foreach ($this->languages as $code => $langId) {
                        $translations[] = [
                            'language_id' => $langId,
                            'block_data' => ['content' => $cuerpo],
                            'is_published' => true,
                        ];
                    }
                    $response = $this->cms->post('/cms/entries/' . $entryId . '/blocks', [
                        'block_id' => $richTextBlockId,
                        'owner_type' => 'entry',
                        'owner_id' => $entryId,
                        'parent_instance_id' => null,
                        'sort_order' => 10,
                        'column_index' => null,
                        'is_active' => true,
                        'block_config' => ['css_class' => ''],
                        'translations' => $translations,
                    ]);
                    $blockId = $this->extractId($response);
                    $this->rememberCmsBlock($blockId, $richTextBlockId, $entryId, null, 10, null);
                    $this->map($runId, 'sn_upa', $id . ':cuerpo', LegacyMigrationCatalog::TARGET_CMS, 'rich_text_block', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'rich text body block');
                    $this->summary['created']['blocks']++;
                }
            }
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyFuncionarios(array $tables, int $runId): void
    {
        $staffRows = $this->visibleRows($tables['sn_funcionarios'] ?? [], 'display');
        usort($staffRows, fn (array $left, array $right): int => $this->numericId($left, 'id') <=> $this->numericId($right, 'id'));
        $staffRows = array_slice($staffRows, 0, 15);

        foreach ($staffRows as $staff) {
            $id = $this->stringValue($staff['id'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }
            $slug = $this->slug((string) ($staff['nombre'] ?: 'staff-' . $id));

            $coverFileId = null;
            $coverPath = $this->stringValue($staff['foto1'] ?? $staff['foto2'] ?? '');
            if ($coverPath !== '') {
                $coverFileId = $this->assetFile('sn_funcionarios', $id, $coverPath, 'staff-' . $id, $runId);
            }

            $wizardExtra = [
                'profession' => $this->stringValue($staff['profesion'] ?? ''),
                'position' => $this->stringValue($staff['cargo'] ?? ''),
                'email' => $this->stringValue($staff['correo'] ?? ''),
                'sort_order' => $this->numericId($staff, 'posicion'),
            ];

            $this->applyCmsEntry(
                'sn_funcionarios',
                $id,
                'personas',
                $slug,
                $this->stringValue($staff['nombre'] ?? 'Funcionario'),
                $this->stringValue($staff['cargo'] ?? ''),
                $wizardExtra,
                $runId,
                $coverFileId
            );
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function applyMuseo(array $tables, int $runId): void
    {
        $museoRows = $tables['sn_museo'] ?? [];
        $exposicionesPageId = 7;

        foreach ($museoRows as $museo) {
            $id = $this->stringValue($museo['id'] ?? '');
            if ($id === '') {
                $this->summary['issues']++;
                continue;
            }

            $cuerpo = $this->stringValue($museo['titulo'] ?? '');
            if ($cuerpo !== '') {
                $richTextBlockId = $this->blockTypes['rich_text'] ?? throw new \RuntimeException('CMS block type rich_text is not configured.');
                $recoveredBlockId = $this->findCmsBlock($exposicionesPageId, $richTextBlockId, null, 150, null, 'page');
                if ($recoveredBlockId !== null) {
                    $this->map($runId, 'sn_museo', $id . ':rich_text', LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $recoveredBlockId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic page body lookup');
                    $this->summary['reused']['blocks']++;
                } else {
                    $translations = [];
                    foreach ($this->languages as $code => $langId) {
                        $translations[] = [
                            'language_id' => $langId,
                            'block_data' => ['content' => $cuerpo],
                            'is_published' => true,
                        ];
                    }
                    $response = $this->cms->post('/cms/entries/' . $exposicionesPageId . '/blocks', [
                        'block_id' => $richTextBlockId,
                        'owner_type' => 'page',
                        'owner_id' => $exposicionesPageId,
                        'parent_instance_id' => null,
                        'sort_order' => 150,
                        'column_index' => null,
                        'is_active' => true,
                        'block_config' => ['css_class' => 'max-w-4xl mx-auto py-8 px-4'],
                        'translations' => $translations,
                    ]);
                    $blockId = $this->extractId($response);
                    $this->rememberCmsBlock($blockId, $richTextBlockId, $exposicionesPageId, null, 150, null, 'page');
                    $this->map($runId, 'sn_museo', $id . ':rich_text', LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'page rich text block');
                    $this->summary['created']['blocks']++;
                }
            }

            $imgPath = $this->stringValue($museo['imagen'] ?? '');
            if ($imgPath !== '') {
                $fileId = $this->assetFile('sn_museo', $id, $imgPath, 'museum-info-' . $id, $runId);
                if ($fileId !== null) {
                    $imageBlockId = $this->blockTypes['image'] ?? throw new \RuntimeException('CMS block type image is not configured.');
                    $recoveredImgBlockId = $this->findCmsBlock($exposicionesPageId, $imageBlockId, null, 160, $fileId, 'page');
                    if ($recoveredImgBlockId !== null) {
                        $this->map($runId, 'sn_museo', $id . ':image', LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $recoveredImgBlockId, LegacyMigrationCatalog::MAP_MAPPED, 'recovered by deterministic page image lookup');
                        $this->summary['reused']['blocks']++;
                    } else {
                        $translations = [];
                        foreach ($this->languages as $code => $langId) {
                            $translations[] = [
                                'language_id' => $langId,
                                'block_data' => ['alt' => 'Museo Templo del Títere y el Payaso', 'caption' => 'Museo Templo del Títere y el Payaso'],
                                'is_published' => true,
                            ];
                        }
                        $response = $this->cms->post('/cms/entries/' . $exposicionesPageId . '/blocks', [
                            'block_id' => $imageBlockId,
                            'owner_type' => 'page',
                            'owner_id' => $exposicionesPageId,
                            'parent_instance_id' => null,
                            'sort_order' => 160,
                            'column_index' => null,
                            'is_active' => true,
                            'block_config' => ['image' => ['source_kind' => 'file', 'file_id' => $fileId]],
                            'translations' => $translations,
                        ]);
                        $blockId = $this->extractId($response);
                        $this->rememberCmsBlock($blockId, $imageBlockId, $exposicionesPageId, null, 160, $fileId, 'page');
                        $this->map($runId, 'sn_museo', $id . ':image', LegacyMigrationCatalog::TARGET_CMS, 'page_block', (string) $blockId, LegacyMigrationCatalog::MAP_MAPPED, 'page image block');
                        $this->summary['created']['blocks']++;
                    }
                }
            }
        }
    }
}
