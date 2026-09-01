<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Transforms and plans legacy Slice C database records.
 *
 * Mappings relate to:
 * - sn_expo / sn_expo_img -> exposiciones
 * - sn_noticias -> news
 * - sn_editorial, sn_prensa, sn_administracion -> publicaciones
 * - sn_upa -> festivales
 * - sn_obra (url=animate) -> festivales (Anímate is its own recurring festival, not a
 *   one-off show — see LEGACY-MAP-018; every edition gets its own festivales item)
 * - sn_funcionarios -> personas
 * - sn_museo -> static page blocks for exposiciones index page
 */
final class LegacySliceCAnalyzer
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
        int $expoLimit = 10,
        int $newsLimit = 20,
        int $pubLimit = 30,
        int $staffLimit = 15
    ): array {
        $issues = [];
        $mappings = [];
        $quarantine = [];
        $assets = [];

        // 1. Exposiciones (sn_expo & sn_expo_img)
        $expoRows = $this->visibleRows($tables['sn_expo'] ?? [], 'display');
        usort($expoRows, fn (array $left, array $right): int => $this->numericId($left, 'id') <=> $this->numericId($right, 'id'));
        $selectedExpos = array_slice($expoRows, 0, max(0, $expoLimit));
        $selectedExpoIds = [];

        $allExpoImages = $tables['sn_expo_img'] ?? [];
        $imagesByExpo = [];
        foreach ($allExpoImages as $img) {
            $eid = $this->stringValue($img['expo_id'] ?? '');
            if ($eid !== '' && ($this->numericId($img, 'display') === 1)) {
                $imagesByExpo[$eid][] = $img;
            }
        }

        foreach ($selectedExpos as $expo) {
            $legacyId = $this->stringValue($expo['id'] ?? '');
            $selectedExpoIds[$legacyId] = true;
            $slug = $this->slug((string) ($expo['url'] ?: $expo['titulo'] ?: 'expo-' . $legacyId));

            if ($legacyId === '') {
                $issues[] = $this->issue('sn_expo', '', 'missing_identity', 'id', 'Exhibition has no legacy identity.', 'error');
                continue;
            }

            if (! $this->validDate($expo['fecha_desde'] ?? null)) {
                $issues[] = $this->issue('sn_expo', $legacyId, 'invalid_date', 'fecha_desde', 'Start date is invalid.', 'warning');
            }
            if (! $this->validDate($expo['fecha_hasta'] ?? null)) {
                $issues[] = $this->issue('sn_expo', $legacyId, 'invalid_date', 'fecha_hasta', 'End date is invalid.', 'warning');
            }

            $mappings[] = $this->mapping('sn_expo', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'exposiciones:' . $slug, $sourceHash);

            $expoImages = $imagesByExpo[$legacyId] ?? [];
            if ($expoImages !== []) {
                // First image becomes cover
                $coverImg = $expoImages[0];
                $coverPath = $this->stringValue($coverImg['img'] ?? '');
                $coverId = $this->stringValue($coverImg['id'] ?? '');
                if ($coverPath !== '') {
                    $assets[] = $this->asset($coverPath) + ['legacy_table' => 'sn_expo_img', 'legacy_id' => $coverId, 'field' => 'img'];
                    $this->appendAssetIssue($issues, 'sn_expo_img', $coverId, $assets[array_key_last($assets)]);
                }

                // Rest map as gallery items
                if (count($expoImages) > 1) {
                    $mappings[] = $this->mapping('sn_expo', $legacyId . ':gallery', LegacyMigrationCatalog::TARGET_CMS, 'gallery', 'exposiciones:' . $slug . ':gallery', $sourceHash);
                    foreach (array_slice($expoImages, 1) as $gImg) {
                        $gPath = $this->stringValue($gImg['img'] ?? '');
                        $gId = $this->stringValue($gImg['id'] ?? '');
                        if ($gPath !== '') {
                            $mappings[] = $this->mapping('sn_expo_img', $gId, LegacyMigrationCatalog::TARGET_CMS, 'gallery_item', 'exposiciones:' . $slug . ':gallery:' . $gId, $sourceHash);
                            $assets[] = $this->asset($gPath) + ['legacy_table' => 'sn_expo_img', 'legacy_id' => $gId, 'field' => 'img'];
                            $this->appendAssetIssue($issues, 'sn_expo_img', $gId, $assets[array_key_last($assets)]);
                        }
                    }
                }
            }
        }

        // 2. Noticias (sn_noticias)
        $newsRows = $this->visibleRows($tables['sn_noticias'] ?? [], 'disp_noticias');
        usort($newsRows, fn (array $left, array $right): int => $this->numericId($left, 'id_noticias') <=> $this->numericId($right, 'id_noticias'));
        $selectedNews = array_slice($newsRows, 0, max(0, $newsLimit));

        foreach ($selectedNews as $news) {
            $legacyId = $this->stringValue($news['id_noticias'] ?? '');
            $slug = $this->slug((string) ($news['url'] ?: $news['titulo'] ?: 'noticia-' . $legacyId));

            if ($legacyId === '') {
                $issues[] = $this->issue('sn_noticias', '', 'missing_identity', 'id_noticias', 'News item has no legacy identity.', 'error');
                continue;
            }

            if (! $this->validDate($news['fecha'] ?? null)) {
                $issues[] = $this->issue('sn_noticias', $legacyId, 'invalid_date', 'fecha', 'Publication date is invalid.', 'warning');
            }

            $mappings[] = $this->mapping('sn_noticias', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'noticias:' . $slug, $sourceHash);

            $coverPath = $this->stringValue($news['foto'] ?? '');
            if ($coverPath !== '') {
                $assets[] = $this->asset($coverPath) + ['legacy_table' => 'sn_noticias', 'legacy_id' => $legacyId, 'field' => 'foto'];
                $this->appendAssetIssue($issues, 'sn_noticias', $legacyId, $assets[array_key_last($assets)]);
            }
        }

        // 3. Publicaciones (sn_editorial, sn_prensa, sn_administracion)
        $pubSources = [
            'sn_editorial' => 'editorial',
            'sn_prensa' => 'press',
            'sn_administracion' => 'transparency',
        ];
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
                $legacyId = $this->stringValue($pub['id'] ?? '');
                $slug = $this->slug((string) (($pub['url'] ?? $pub['link'] ?? $pub['titulo'] ?? '') ?: $type . '-' . $legacyId));

                if ($legacyId === '') {
                    $issues[] = $this->issue($table, '', 'missing_identity', 'id', 'Publication has no legacy identity.', 'error');
                    continue;
                }

                $mappings[] = $this->mapping($table, $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'publicaciones:' . $slug, $sourceHash);

                $pdfPath = $this->stringValue($pub['archivo'] ?? '');
                if ($pdfPath !== '') {
                    $mappings[] = $this->mapping($table, $legacyId . ':pdf', LegacyMigrationCatalog::TARGET_CMS, 'file', 'publicaciones:' . $slug . ':document', $sourceHash);
                    $assets[] = $this->asset($pdfPath) + ['legacy_table' => $table, 'legacy_id' => $legacyId, 'field' => 'archivo'];
                    $this->appendAssetIssue($issues, $table, $legacyId, $assets[array_key_last($assets)]);
                }

                $coverPath = $this->stringValue($pub['foto'] ?? '');
                if ($coverPath !== '') {
                    $assets[] = $this->asset($coverPath) + ['legacy_table' => $table, 'legacy_id' => $legacyId, 'field' => 'foto'];
                    $this->appendAssetIssue($issues, $table, $legacyId, $assets[array_key_last($assets)]);
                }
            }
        }

        // 4. Festivales (sn_upa)
        $upaRows = $tables['sn_upa'] ?? [];
        foreach ($upaRows as $upa) {
            $legacyId = $this->stringValue($upa['id_upa'] ?? '');
            if ($legacyId === '') {
                $issues[] = $this->issue('sn_upa', '', 'missing_identity', 'id_upa', 'Upa festival has no legacy identity.', 'error');
                continue;
            }
            $slug = 'upa-chalupa-2019'; // VII Encuentro Upa Chalupa 2019
            $mappings[] = $this->mapping('sn_upa', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'festivales:' . $slug, $sourceHash);
        }

        // 4b. Festivales — Anímate editions (sn_obra where url = 'animate')
        $animateRows = array_values(array_filter(
            $tables['sn_obra'] ?? [],
            fn (array $row): bool => $this->stringValue($row['url'] ?? '') === 'animate'
        ));
        foreach ($animateRows as $animate) {
            $legacyId = $this->stringValue($animate['id_obra'] ?? '');
            if ($legacyId === '') {
                $issues[] = $this->issue('sn_obra', '', 'missing_identity', 'id_obra', 'Anímate edition has no legacy identity.', 'error');
                continue;
            }
            $slug = 'animate-2024'; // IX Encuentro Internacional de Títeres Anímate
            $mappings[] = $this->mapping('sn_obra', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'festivales:' . $slug, $sourceHash);

            $imgPath = $this->stringValue($animate['foto_obra'] ?? '');
            if ($imgPath !== '') {
                $assets[] = $this->asset($imgPath) + ['legacy_table' => 'sn_obra', 'legacy_id' => $legacyId, 'field' => 'foto_obra'];
                $this->appendAssetIssue($issues, 'sn_obra', $legacyId, $assets[array_key_last($assets)]);
            }
        }

        // 5. Funcionarios / Staff (sn_funcionarios)
        $staffRows = $this->visibleRows($tables['sn_funcionarios'] ?? [], 'display');
        usort($staffRows, fn (array $left, array $right): int => $this->numericId($left, 'id') <=> $this->numericId($right, 'id'));
        $selectedStaff = array_slice($staffRows, 0, max(0, $staffLimit));

        foreach ($selectedStaff as $staff) {
            $legacyId = $this->stringValue($staff['id'] ?? '');
            $slug = $this->slug((string) ($staff['nombre'] ?: 'staff-' . $legacyId));

            if ($legacyId === '') {
                $issues[] = $this->issue('sn_funcionarios', '', 'missing_identity', 'id', 'Staff member has no legacy identity.', 'error');
                continue;
            }

            $mappings[] = $this->mapping('sn_funcionarios', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'entry', 'personas:' . $slug, $sourceHash);

            foreach (['foto1', 'foto2'] as $field) {
                $photoPath = $this->stringValue($staff[$field] ?? '');
                if ($photoPath !== '') {
                    $assets[] = $this->asset($photoPath) + ['legacy_table' => 'sn_funcionarios', 'legacy_id' => $legacyId, 'field' => $field];
                    $this->appendAssetIssue($issues, 'sn_funcionarios', $legacyId, $assets[array_key_last($assets)]);
                }
            }
        }

        // 6. Museo info (sn_museo)
        $museoRows = $tables['sn_museo'] ?? [];
        foreach ($museoRows as $museo) {
            $legacyId = $this->stringValue($museo['id'] ?? '');
            if ($legacyId === '') {
                $issues[] = $this->issue('sn_museo', '', 'missing_identity', 'id', 'Museum info has no legacy identity.', 'error');
                continue;
            }
            // Maps to the index page of exhibitions (ID 7)
            $mappings[] = $this->mapping('sn_museo', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'page_block', 'exposiciones:blocks:sn_museo', $sourceHash);

            $imgPath = $this->stringValue($museo['imagen'] ?? '');
            if ($imgPath !== '') {
                $assets[] = $this->asset($imgPath) + ['legacy_table' => 'sn_museo', 'legacy_id' => $legacyId, 'field' => 'imagen'];
                $this->appendAssetIssue($issues, 'sn_museo', $legacyId, $assets[array_key_last($assets)]);
            }
        }

        // Build summary metrics
        $cmsEntriesCount = 0;
        $cmsBlocksCount = 0;
        foreach ($mappings as $map) {
            if ($map['target_type'] === 'entry') {
                $cmsEntriesCount++;
            }
            if (in_array($map['target_type'], ['gallery', 'gallery_item', 'document_block', 'page_block'], true)) {
                $cmsBlocksCount++;
            }
        }

        $summary = [
            'slice' => 'C',
            'analyzer' => self::class,
            'source' => ['path' => $sourcePath, 'sha256' => $sourceHash],
            'slice_rows_selected' => [
                'exposiciones' => count($selectedExpos),
                'noticias' => count($selectedNews),
                'publicaciones' => $pubCount,
                'festivales' => count($upaRows) + count($animateRows),
                'personas' => count($selectedStaff),
                'museo' => count($museoRows),
            ],
            'targets_planned' => [
                'cms_entries' => $cmsEntriesCount,
                'cms_blocks' => $cmsBlocksCount,
            ],
            'issues' => count($issues),
            'quarantine' => count($quarantine),
            'assets' => count($assets),
        ];

        return [
            'summary' => $summary,
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
    private function visibleRows(array $rows, string $displayField = 'display'): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => $this->numericId($row, $displayField) === 1));
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function numericId(mixed $row, string $field): int
    {
        $value = $row[$field] ?? null;
        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function slug(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return 'sin-identidad';
        }

        $ascii = null;
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $ascii = preg_replace('/\p{Mn}+/u', '', $normalized);
            }
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = $value;
        }

        $value = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? $ascii;

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
            $asset['field'] ?? 'asset_path',
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
