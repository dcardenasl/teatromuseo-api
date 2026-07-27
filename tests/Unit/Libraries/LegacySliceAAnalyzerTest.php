<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacySliceAAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacySliceAAnalyzerTest extends TestCase
{
    public function testSliceAPlansCanonicalWorksOperationalOccurrencesAndDuplicateVideos(): void
    {
        $report = (new LegacySliceAAnalyzer())->analyze([
            'sn_compania' => [
                ['id_compania' => '1', 'nombre_compania' => 'Compañía Uno', 'display_comp' => '1'],
            ],
            'sn_obra' => [
                ['id_obra' => '1', 'titulo_obra' => 'Obra Uno', 'url' => 'obra-uno', 'fecha_obra' => '2020-01-01', 'id_compania' => '1', 'display' => '1', 'foto_obra' => '/images/obra.jpg'],
                ['id_obra' => '2', 'titulo_obra' => 'Obra Uno', 'url' => 'obra-uno', 'fecha_obra' => '2020-01-02', 'id_compania' => '1', 'display' => '1', 'foto_obra' => '/images/obra-2.jpg'],
            ],
            'sn_slider_cartelera' => [
                ['id_slider' => '10', 'id_obra' => '1', 'url_sl' => '/images/gallery.jpg', 'display' => '1'],
            ],
            'sn_youtube' => [
                ['id_youtube' => '20', 'url' => 'video-id', 'display' => '1'],
                ['id_youtube' => '21', 'url' => 'video-id', 'display' => '1'],
            ],
        ], '/tmp/fixture.sql', str_repeat('a', 64), 10, 5, 3);

        $this->assertSame(1, $report['summary']['slice_rows_selected']['canonical_works']);
        $this->assertSame(2, $report['summary']['targets_planned']['event_occurrences']);
        $this->assertSame(1, $report['summary']['targets_planned']['event_events']);
        $this->assertSame(1, $report['summary']['targets_planned']['cms_gallery_items']);
        $this->assertSame(1, $report['summary']['slice_rows_selected']['videos']);
        $this->assertContains(LegacyMigrationCatalog::MAP_PLANNED, array_column($report['mappings'], 'status'));
        $this->assertContains(LegacyMigrationCatalog::MAP_DUPLICATE, array_column($report['mappings'], 'status'));
        $this->assertNotEmpty(array_filter($report['issues'], static fn (array $issue): bool => $issue['issue_class'] === 'duplicate_video'));
    }

    public function testUnknownGalleryRowsAreQuarantined(): void
    {
        $report = (new LegacySliceAAnalyzer())->analyze([
            'sn_compania' => [],
            'sn_obra' => [],
            'sn_slider_cartelera' => [
                ['id_slider' => '404', 'id_obra' => '999', 'url_sl' => '/images/missing.jpg', 'display' => '1'],
            ],
            'sn_youtube' => [],
        ], '/tmp/fixture.sql', str_repeat('a', 64));

        $this->assertSame(1, $report['summary']['quarantine']);
        $this->assertSame(LegacyMigrationCatalog::MAP_QUARANTINED, $report['mappings'][0]['status']);
        $this->assertSame('sn_slider_cartelera', $report['quarantine'][0]['legacy_table']);
    }
}
