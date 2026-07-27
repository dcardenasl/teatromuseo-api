<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Builds the write-free plan for the course migration slice.
 *
 * sn_escuela is the canonical course source. sn_cursos is treated as a
 * supplemental source when the same course ID exists in both tables; it must
 * never create a second CMS entry. Images, teachers and documents retain
 * their source identity so the apply phase can be retried safely.
 */
final class LegacySliceBAnalyzer
{
    public function __construct(private readonly ?LegacyAssetResolver $assetResolver = null)
    {
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     * @return array{
     *     summary: array<string, mixed>,
     *     mappings: list<array<string, mixed>>,
     *     issues: list<array<string, mixed>>,
     *     quarantine: list<array<string, mixed>>,
     *     assets: list<array<string, mixed>>
     * }
     */
    public function analyze(
        array $tables,
        string $sourcePath,
        string $sourceHash,
        int $courseLimit = 3,
        int $teacherLimit = 20
    ): array {
        $allCourses = $this->visibleRows($tables['sn_escuela'] ?? [], 'curso_display');
        usort($allCourses, fn (array $left, array $right): int => $this->numericId($left, 'curso_id') <=> $this->numericId($right, 'curso_id'));
        $selectedCourses = array_slice($allCourses, 0, max(0, $courseLimit));
        $selectedCourseIds = [];
        foreach ($selectedCourses as $course) {
            $selectedCourseIds[$this->stringValue($course['curso_id'] ?? '')] = true;
        }

        $allCourseIds = [];
        foreach ($tables['sn_escuela'] ?? [] as $course) {
            $id = $this->stringValue($course['curso_id'] ?? '');
            if ($id !== '') {
                $allCourseIds[$id] = true;
            }
        }

        $supplementsById = [];
        foreach ($tables['sn_cursos'] ?? [] as $supplement) {
            $id = $this->stringValue($supplement['id'] ?? '');
            if ($id !== '') {
                $supplementsById[$id] = $supplement;
            }
        }

        $categoryIds = [];
        foreach ($tables['sn_categoria_escuela'] ?? [] as $category) {
            $id = $this->stringValue($category['id'] ?? '');
            if ($id !== '') {
                $categoryIds[$id] = true;
            }
        }

        /** @var array<string, array<string, mixed>> $selectedTeachers */
        $selectedTeachers = [];
        $teacherRows = $this->visibleRows($tables['sn_profesor'] ?? [], 'profesor_display');
        usort($teacherRows, fn (array $left, array $right): int => $this->numericId($left, 'profesor_id') <=> $this->numericId($right, 'profesor_id'));
        foreach ($selectedCourses as $course) {
            $courseId = $this->stringValue($course['curso_id'] ?? '');
            foreach ($teacherRows as $teacher) {
                if ($this->stringValue($teacher['profesor_curso'] ?? '') !== $courseId) {
                    continue;
                }
                $teacherId = $this->stringValue($teacher['profesor_id'] ?? '');
                if ($teacherId !== '') {
                    $selectedTeachers[$teacherId] = $teacher;
                }
            }
        }
        $selectedTeachers = array_slice($selectedTeachers, 0, max(0, $teacherLimit), true);

        /** @var list<array<string, mixed>> $issues */
        $issues = [];
        /** @var list<array<string, mixed>> $mappings */
        $mappings = [];
        /** @var list<array<string, mixed>> $quarantine */
        $quarantine = [];
        /** @var list<array<string, mixed>> $assets */
        $assets = [];
        $teacherIds = [];
        $galleryCount = 0;
        $documentCount = 0;
        $externalLinkCount = 0;
        $supplementCount = 0;
        $galleryOwners = [];

        foreach ($selectedCourses as $course) {
            $courseId = $this->stringValue($course['curso_id'] ?? '');
            $courseKey = $this->courseKey($course);
            if ($courseId === '') {
                $issues[] = $this->issue('sn_escuela', '', 'missing_identity', 'curso_id', 'Course has no legacy identity.', 'error');
                continue;
            }

            $mappings[] = $this->mapping('sn_escuela', $courseId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'cursos:' . $courseKey, $sourceHash);

            $categoryId = $this->stringValue($course['curso_categoria'] ?? '');
            if ($categoryId !== '' && ! isset($categoryIds[$categoryId])) {
                $issues[] = $this->issue('sn_escuela', $courseId, 'fk_missing', 'curso_categoria', 'Course category is not present in sn_categoria_escuela.', 'warning');
            }

            foreach (['curso_fecha_inicio', 'curso_fecha_termino'] as $field) {
                if (! $this->validDate($course[$field] ?? null)) {
                    $issues[] = $this->issue('sn_escuela', $courseId, 'invalid_date', $field, 'Date will be converted to NULL and reviewed before apply.', 'warning');
                }
            }

            $supplement = $supplementsById[$courseId] ?? null;
            if ($supplement !== null) {
                $supplementCount++;
                $supplementMapping = $this->mapping('sn_cursos', $courseId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'cursos:' . $courseKey, $sourceHash);
                $supplementMapping['status'] = LegacyMigrationCatalog::MAP_SUPPLEMENTAL;
                $mappings[] = $supplementMapping;

                foreach (['date_start', 'date_end'] as $field) {
                    if ($this->stringValue($supplement[$field] ?? '') !== '' && ! $this->validDate($supplement[$field] ?? null)) {
                        $issues[] = $this->issue('sn_cursos', $courseId, 'invalid_date', $field, 'Supplemental date will be converted to NULL and reviewed before apply.', 'warning');
                    }
                }

                $coverPath = $this->stringValue($supplement['image_cover'] ?? '');
                if ($coverPath !== '') {
                    $assets[] = $this->asset($coverPath) + ['legacy_table' => 'sn_cursos', 'legacy_id' => $courseId, 'field' => 'image_cover'];
                    $this->appendAssetIssue($issues, 'sn_cursos', $courseId, $assets[array_key_last($assets)]);
                }

                $pdfPath = $this->stringValue($supplement['pdf_file'] ?? '');
                if ($pdfPath !== '') {
                    $documentCount++;
                    $mappings[] = $this->mapping('sn_cursos', $courseId . ':pdf', LegacyMigrationCatalog::TARGET_CMS, 'file', 'cursos:' . $courseKey . ':document', $sourceHash);
                    $assets[] = $this->asset($pdfPath) + ['legacy_table' => 'sn_cursos', 'legacy_id' => $courseId, 'field' => 'pdf_file'];
                    $this->appendAssetIssue($issues, 'sn_cursos', $courseId, $assets[array_key_last($assets)]);
                }

                if ($this->stringValue($supplement['google_forms_link'] ?? '') !== '') {
                    $externalLinkCount++;
                    $mappings[] = $this->mapping('sn_cursos', $courseId . ':google-form', LegacyMigrationCatalog::TARGET_CMS, 'external_link', 'cursos:' . $courseKey . ':registration', $sourceHash);
                }
                if ($this->stringValue($supplement['youtube_video_link'] ?? '') !== '') {
                    $externalLinkCount++;
                    $mappings[] = $this->mapping('sn_cursos', $courseId . ':youtube', LegacyMigrationCatalog::TARGET_CMS, 'video_reference', 'cursos:' . $courseKey . ':video', $sourceHash);
                }
            }

            foreach ($selectedTeachers as $teacher) {
                if ($this->stringValue($teacher['profesor_curso'] ?? '') !== $courseId) {
                    continue;
                }
                $teacherId = $this->stringValue($teacher['profesor_id'] ?? '');
                if ($teacherId === '') {
                    $issues[] = $this->issue('sn_profesor', '', 'missing_identity', 'profesor_id', 'Teacher has no legacy identity.', 'warning');
                    continue;
                }
                $teacherIds[$teacherId] = $teacher;
                $mappings[] = $this->mapping('sn_profesor', $teacherId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'personas:' . $this->teacherKey($teacher), $sourceHash);
                $mappings[] = $this->mapping('sn_profesor', $teacherId . ':course:' . $courseId, LegacyMigrationCatalog::TARGET_CMS, 'entry_reference', 'cursos:' . $courseKey . ':instructor:' . $teacherId, $sourceHash);
            }

            foreach ($this->visibleRows($tables['sn_escuela_img'] ?? [], 'escuela_img_display') as $image) {
                if ($this->stringValue($image['curso_id'] ?? '') !== $courseId) {
                    continue;
                }
                $imageId = $this->stringValue($image['escuela_img_id'] ?? '');
                $galleryCount++;
                if (! isset($galleryOwners[$courseId])) {
                    $galleryOwners[$courseId] = true;
                    $mappings[] = $this->mapping(
                        'sn_escuela',
                        $courseId . ':gallery',
                        LegacyMigrationCatalog::TARGET_CMS,
                        'gallery',
                        'cursos:' . $courseKey . ':gallery',
                        $sourceHash
                    );
                }
                $mappings[] = $this->mapping('sn_escuela_img', $imageId, LegacyMigrationCatalog::TARGET_CMS, 'gallery_item', 'cursos:' . $courseKey . ':gallery:' . $imageId, $sourceHash);
                $assetPath = $this->stringValue($image['escuela_img_url'] ?? '');
                $assets[] = $this->asset($assetPath) + ['legacy_table' => 'sn_escuela_img', 'legacy_id' => $imageId, 'field' => 'escuela_img_url'];
                $this->appendAssetIssue($issues, 'sn_escuela_img', $imageId, $assets[array_key_last($assets)]);
            }
        }

        foreach ($this->visibleRows($tables['sn_escuela_img'] ?? [], 'escuela_img_display') as $image) {
            $courseId = $this->stringValue($image['curso_id'] ?? '');
            if ($courseId !== '' && ! isset($allCourseIds[$courseId])) {
                $imageId = $this->stringValue($image['escuela_img_id'] ?? '');
                $issues[] = $this->issue('sn_escuela_img', $imageId, 'fk_missing', 'curso_id', 'Course image points to an unknown course.', 'warning');
                $mappings[] = $this->quarantinedMapping('sn_escuela_img', $imageId, 'orphan-course-image:' . $imageId, $sourceHash);
                $quarantine[] = $this->quarantine('sn_escuela_img', $imageId, 'fk_missing', 'Course image points to an unknown course.', $image);
            }
        }

        foreach ($tables['sn_cursos'] ?? [] as $supplement) {
            $courseId = $this->stringValue($supplement['id'] ?? '');
            if ($courseId !== '' && ! isset($allCourseIds[$courseId])) {
                $issues[] = $this->issue('sn_cursos', $courseId, 'orphan_supplement', 'id', 'Supplemental course row has no canonical sn_escuela row.', 'warning');
                $mappings[] = $this->quarantinedMapping('sn_cursos', $courseId, 'orphan-course-supplement:' . $courseId, $sourceHash);
                $quarantine[] = $this->quarantine('sn_cursos', $courseId, 'orphan_supplement', 'Supplemental course row has no canonical sn_escuela row.', $supplement);
            }
        }

        $teacherCount = count($teacherIds);
        return [
            'summary' => [
                'slice' => 'B',
                'mode' => LegacyMigrationCatalog::MODE_DRY_RUN,
                'source' => ['path' => $sourcePath, 'sha256' => $sourceHash],
                'legacy_rows_read' => [
                    'sn_escuela' => count($tables['sn_escuela'] ?? []),
                    'sn_cursos' => count($tables['sn_cursos'] ?? []),
                    'sn_escuela_img' => count($tables['sn_escuela_img'] ?? []),
                    'sn_profesor' => count($tables['sn_profesor'] ?? []),
                    'sn_categoria_escuela' => count($tables['sn_categoria_escuela'] ?? []),
                ],
                'slice_rows_selected' => [
                    'courses' => count($selectedCourses),
                    'course_supplements' => $supplementCount,
                    'teachers' => $teacherCount,
                    'gallery_items' => $galleryCount,
                    'documents' => $documentCount,
                    'external_links' => $externalLinkCount,
                ],
                'targets_planned' => [
                    'cms_entries' => count($selectedCourses) + $teacherCount,
                    'cms_gallery_items' => $galleryCount,
                    'cms_files' => $documentCount,
                    'cms_relationships' => count(array_filter($mappings, static fn (array $mapping): bool => in_array($mapping['target_type'], ['entry_reference', 'external_link', 'video_reference'], true))),
                ],
                'issues' => count($issues),
                'quarantine' => count($quarantine),
            ],
            'mappings' => $mappings,
            'issues' => $issues,
            'quarantine' => $quarantine,
            'assets' => $assets,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function visibleRows(array $rows, string $field): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => ! array_key_exists($field, $row) || (int) $row[$field] === 1));
    }

    /** @param array<string, mixed> $row */
    private function courseKey(array $row): string
    {
        $slug = $this->stringValue($row['url'] ?? '');
        if ($slug === '') {
            $slug = $this->stringValue($row['curso_titulo'] ?? 'curso-' . ($row['curso_id'] ?? 'unknown'));
        }

        $id = $this->stringValue($row['curso_id'] ?? '');

        return $this->slug($slug) . ($id !== '' ? '-' . $this->slug($id) : '');
    }

    /** @param array<string, mixed> $row */
    private function teacherKey(array $row): string
    {
        $name = $this->stringValue($row['profesor_nombre'] ?? 'persona');

        return $this->slug($name) . '-' . $this->stringValue($row['profesor_id'] ?? 'unknown');
    }

    /** @param array<string, mixed> $row */
    private function numericId(array $row, string $field): int
    {
        return (int) ($row[$field] ?? 0);
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-') ?: 'sin-identidad';
    }

    private function validDate(mixed $value): bool
    {
        $date = $this->stringValue($value);
        if ($date === '' || $date === '0000-00-00') {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** @return array<string, mixed> */
    private function mapping(string $table, string $legacyId, string $targetSystem, string $targetType, string $targetKey, string $sourceHash): array
    {
        return [
            'legacy_table' => $table,
            'legacy_id' => $legacyId,
            'target_system' => $targetSystem,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'target_id' => null,
            'source_hash' => $sourceHash,
            'status' => LegacyMigrationCatalog::MAP_PLANNED,
        ];
    }

    /** @return array<string, mixed> */
    private function quarantinedMapping(string $table, string $legacyId, string $targetKey, string $sourceHash): array
    {
        $mapping = $this->mapping($table, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'quarantine', $targetKey, $sourceHash);
        $mapping['status'] = LegacyMigrationCatalog::MAP_QUARANTINED;

        return $mapping;
    }

    /**
     * @param array<string, mixed> $rawRow
     * @return array{legacy_table: string, legacy_id: string, error_class: string, error_message: string, raw_row: array<string, mixed>}
     */
    private function quarantine(string $table, string $legacyId, string $errorClass, string $errorMessage, array $rawRow): array
    {
        return [
            'legacy_table' => $table,
            'legacy_id' => $legacyId,
            'error_class' => $errorClass,
            'error_message' => $errorMessage,
            'raw_row' => $rawRow,
        ];
    }

    /** @return array<string, mixed> */
    private function issue(string $table, string $legacyId, string $class, string $field, string $note, string $severity): array
    {
        return [
            'legacy_table' => $table,
            'legacy_id' => $legacyId,
            'issue_class' => $class,
            'severity' => $severity,
            'field' => $field,
            'original_value' => null,
            'applied_value' => null,
            'note' => $note,
        ];
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<string, mixed> $asset
     */
    private function appendAssetIssue(array &$issues, string $table, string $legacyId, array $asset): void
    {
        if (($asset['status'] ?? null) === 'resolved') {
            return;
        }

        $issues[] = $this->issue(
            $table,
            $legacyId,
            ($asset['status'] ?? null) === 'missing' ? 'asset_missing' : 'asset_invalid',
            'asset_path',
            (string) ($asset['reason'] ?? 'Asset could not be resolved.'),
            'warning'
        );
    }

    /** @return array<string, mixed> */
    private function asset(string $path): array
    {
        if ($this->assetResolver === null) {
            return [
                'status' => 'missing',
                'source_path' => $path,
                'relative_path' => null,
                'absolute_path' => null,
                'sha256' => null,
                'size' => null,
                'mime_type' => null,
                'reason' => 'asset_root_not_configured',
            ];
        }

        return $this->assetResolver->resolve($path);
    }
}
