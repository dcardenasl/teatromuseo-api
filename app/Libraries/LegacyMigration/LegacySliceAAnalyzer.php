<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Builds a compact, write-free transformation plan for the first migration
 * slice. It produces target keys rather than target IDs because no domain
 * content is created during a dry-run.
 */
final class LegacySliceAAnalyzer
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
        int $workLimit = 10,
        int $videoLimit = 5,
        int $companyLimit = 3
    ): array {
        $workRows = $this->visibleRows($tables['sn_obra'] ?? [], 'display');
        usort($workRows, fn (array $left, array $right): int => $this->numericId($left, 'id_obra') <=> $this->numericId($right, 'id_obra'));
        $selectedWorks = array_slice($workRows, 0, max(0, $workLimit));
        $selectedWorkIds = [];
        foreach ($selectedWorks as $work) {
            $selectedWorkIds[$this->stringValue($work['id_obra'] ?? '')] = true;
        }

        $issues = [];
        $mappings = [];
        $quarantine = [];
        $assets = [];
        $workGroups = [];
        foreach ($selectedWorks as $work) {
            $legacyId = $this->stringValue($work['id_obra'] ?? '');
            $workKey = $this->workKey($work);
            $workGroups[$workKey][] = $work;

            if ($legacyId === '') {
                $issues[] = $this->issue('sn_obra', '', 'missing_identity', 'id_obra', 'Work has no legacy identity.', 'error');
            }
            if (! $this->validDate($work['fecha_obra'] ?? null)) {
                $issues[] = $this->issue('sn_obra', $legacyId, 'invalid_date', 'fecha_obra', 'Date will require review before apply.', 'warning');
            }
            if (! $this->hasRow($tables['sn_compania'] ?? [], 'id_compania', $work['id_compania'] ?? null)) {
                $issues[] = $this->issue('sn_obra', $legacyId, 'fk_missing', 'id_compania', 'Referenced company is not present in the selected source rows.', 'warning');
            }

            $mappings[] = $this->mapping('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'obras:' . $workKey, $sourceHash);
            $mappings[] = $this->mapping('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'event', 'function:' . $workKey, $sourceHash);
            $mappings[] = $this->mapping('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_EVENT, 'occurrence', 'sn_obra:' . $legacyId, $sourceHash);

            $asset = $this->asset((string) ($work['foto_obra'] ?? ''));
            $assets[] = $asset + ['legacy_table' => 'sn_obra', 'legacy_id' => $legacyId, 'field' => 'foto_obra'];
            if ($asset['status'] !== 'resolved') {
                $issues[] = $this->assetIssue('sn_obra', $legacyId, $asset);
            }
        }

        foreach ($workGroups as $workKey => $group) {
            if (count($group) > 1) {
                foreach ($group as $index => $work) {
                    $legacyId = $this->stringValue($work['id_obra'] ?? '');
                    if ($index > 0) {
                        $this->markMappingsDuplicate($mappings, 'sn_obra', $legacyId, ['entry', 'event']);
                    }
                    $issues[] = $this->issue(
                        'sn_obra',
                        $legacyId,
                        'canonical_group',
                        'url',
                        "Rows grouped under canonical work '{$workKey}'.",
                        'info'
                    );
                }
            }
        }

        $companyRows = $this->visibleRows($tables['sn_compania'] ?? [], 'display_comp');
        $referencedCompanyIds = [];
        foreach ($selectedWorks as $work) {
            $companyId = $this->stringValue($work['id_compania'] ?? '');
            if ($companyId !== '' && ! in_array($companyId, $referencedCompanyIds, true)) {
                $referencedCompanyIds[] = $companyId;
            }
        }
        $companyIds = array_fill_keys(array_slice($referencedCompanyIds, 0, max(0, $companyLimit)), true);
        foreach ($companyRows as $company) {
            if (count($companyIds) >= max(0, $companyLimit)) {
                break;
            }
            $companyId = $this->stringValue($company['id_compania'] ?? '');
            if ($companyId !== '') {
                $companyIds[$companyId] = true;
            }
        }
        $companyById = [];
        foreach ($companyRows as $company) {
            $id = $this->stringValue($company['id_compania'] ?? '');
            if ($id !== '' && isset($companyIds[$id])) {
                $companyById[$id] = $company;
                $mappings[] = $this->mapping(
                    'sn_compania',
                    $id,
                    LegacyMigrationCatalog::TARGET_CMS,
                    'entry',
                    'companias:' . $this->slug((string) ($company['nombre_compania'] ?? 'company-' . $id)),
                    $sourceHash
                );
            }
        }
        foreach ($companyIds as $companyId => $_) {
            if (! isset($companyById[$companyId])) {
                $issues[] = $this->issue('sn_compania', (string) $companyId, 'fk_missing', 'id_compania', 'Company referenced by the slice is not visible in the source.', 'warning');
            }
        }

        $allWorkIds = [];
        foreach ($tables['sn_obra'] ?? [] as $work) {
            $allWorkIds[$this->stringValue($work['id_obra'] ?? '')] = true;
        }
        $galleryCount = 0;
        $galleryOwners = [];
        foreach ($this->visibleRows($tables['sn_slider_cartelera'] ?? [], 'display') as $image) {
            $legacyId = $this->stringValue($image['id_slider'] ?? '');
            $workId = $this->stringValue($image['id_obra'] ?? '');
            if (! isset($selectedWorkIds[$workId])) {
                if (! isset($allWorkIds[$workId])) {
                    $issues[] = $this->issue('sn_slider_cartelera', $legacyId, 'fk_missing', 'id_obra', 'Gallery item points to an unknown work.', 'warning');
                    $orphanMapping = $this->mapping(
                        'sn_slider_cartelera',
                        $legacyId,
                        LegacyMigrationCatalog::TARGET_CMS,
                        'gallery_item',
                        'orphan-gallery:' . $legacyId,
                        $sourceHash
                    );
                    $orphanMapping['status'] = LegacyMigrationCatalog::MAP_QUARANTINED;
                    $mappings[] = $orphanMapping;
                    $quarantine[] = $this->quarantine(
                        'sn_slider_cartelera',
                        $legacyId,
                        'fk_missing',
                        'Gallery item points to an unknown work.',
                        $image
                    );
                }
                continue;
            }
            $galleryCount++;
            $work = $this->findRow($selectedWorks, 'id_obra', $workId);
            if (! isset($galleryOwners[$workId])) {
                $galleryOwners[$workId] = true;
                $mappings[] = $this->mapping(
                    'sn_obra',
                    $workId . ':gallery',
                    LegacyMigrationCatalog::TARGET_CMS,
                    'gallery',
                    'obras:' . $this->workKey($work ?? []) . ':gallery',
                    $sourceHash
                );
            }
            $mappings[] = $this->mapping(
                'sn_slider_cartelera',
                $legacyId,
                LegacyMigrationCatalog::TARGET_CMS,
                'gallery_item',
                'obras:' . $this->workKey($work ?? []) . ':gallery:' . $legacyId,
                $sourceHash
            );
            $asset = $this->asset((string) ($image['url_sl'] ?? ''));
            $assets[] = $asset + ['legacy_table' => 'sn_slider_cartelera', 'legacy_id' => $legacyId, 'field' => 'url_sl'];
            if ($asset['status'] !== 'resolved') {
                $issues[] = $this->assetIssue('sn_slider_cartelera', $legacyId, $asset);
            }
        }

        $videoGroups = [];
        foreach ($this->visibleRows($tables['sn_youtube'] ?? [], 'display') as $video) {
            $videoKey = $this->stringValue($video['url'] ?? '');
            if ($videoKey === '') {
                $issues[] = $this->issue('sn_youtube', $this->stringValue($video['id_youtube'] ?? ''), 'missing_identity', 'url', 'Video has no provider ID.', 'warning');
                continue;
            }
            $videoGroups[$videoKey][] = $video;
        }
        $videoGroups = array_slice($videoGroups, 0, max(0, $videoLimit), true);
        foreach ($videoGroups as $videoKey => $group) {
            $canonical = $group[0];
            $legacyId = $this->stringValue($canonical['id_youtube'] ?? '');
            $mappings[] = $this->mapping('sn_youtube', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'videos:' . $videoKey, $sourceHash);
            if (count($group) > 1) {
                foreach (array_slice($group, 1) as $duplicate) {
                    $duplicateId = $this->stringValue($duplicate['id_youtube'] ?? '');
                    $duplicateMapping = $this->mapping('sn_youtube', $duplicateId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'videos:' . $videoKey, $sourceHash);
                    $duplicateMapping['status'] = LegacyMigrationCatalog::MAP_DUPLICATE;
                    $mappings[] = $duplicateMapping;
                    $issues[] = $this->issue(
                        'sn_youtube',
                        $duplicateId,
                        'duplicate_video',
                        'url',
                        "Duplicate provider ID absorbed by sn_youtube:{$legacyId}.",
                        'info'
                    );
                }
            }
        }

        return [
            'summary' => [
                'slice' => 'A',
                'mode' => LegacyMigrationCatalog::MODE_DRY_RUN,
                'source' => ['path' => $sourcePath, 'sha256' => $sourceHash],
                'legacy_rows_read' => [
                    'sn_compania' => count($tables['sn_compania'] ?? []),
                    'sn_obra' => count($tables['sn_obra'] ?? []),
                    'sn_slider_cartelera' => count($tables['sn_slider_cartelera'] ?? []),
                    'sn_youtube' => count($tables['sn_youtube'] ?? []),
                ],
                'slice_rows_selected' => [
                    'companies' => count($companyById),
                    'works_rows' => count($selectedWorks),
                    'canonical_works' => count($workGroups),
                    'gallery_items' => $galleryCount,
                    'videos' => count($videoGroups),
                ],
                'targets_planned' => [
                    'cms_entries' => count($companyById) + count($workGroups) + count($videoGroups),
                    'event_events' => count($workGroups),
                    'event_occurrences' => count($selectedWorks),
                    'cms_gallery_items' => $galleryCount,
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
    private function workKey(array $row): string
    {
        $value = trim((string) ($row['url'] ?? ''));
        if ($value !== '') {
            return $this->slug($value);
        }

        return $this->slug((string) ($row['titulo_obra'] ?? 'obra-' . ($row['id_obra'] ?? 'unknown')));
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-') ?: 'sin-identidad';
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

    private function validDate(mixed $value): bool
    {
        $date = $this->stringValue($value);
        if ($date === '' || $date === '0000-00-00') {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** @param list<array<string, mixed>> $rows */
    private function hasRow(array $rows, string $field, mixed $value): bool
    {
        return $this->findRow($rows, $field, $this->stringValue($value)) !== null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function findRow(array $rows, string $field, string $value): ?array
    {
        foreach ($rows as $row) {
            if ($this->stringValue($row[$field] ?? null) === $value) {
                return $row;
            }
        }

        return null;
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

    /**
     * @param list<array<string, mixed>> $mappings
     * @param list<string> $targetTypes
     */
    private function markMappingsDuplicate(array &$mappings, string $table, string $legacyId, array $targetTypes): void
    {
        foreach ($mappings as &$mapping) {
            if (
                $mapping['legacy_table'] === $table
                && $mapping['legacy_id'] === $legacyId
                && in_array($mapping['target_type'], $targetTypes, true)
            ) {
                $mapping['status'] = LegacyMigrationCatalog::MAP_DUPLICATE;
            }
        }
        unset($mapping);
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

    /**
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function assetIssue(string $table, string $legacyId, array $asset): array
    {
        return $this->issue(
            $table,
            $legacyId,
            $asset['status'] === 'missing' ? 'asset_missing' : 'asset_invalid',
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
