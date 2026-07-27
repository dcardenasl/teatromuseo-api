<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacySliceBAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacySliceBAnalyzerTest extends TestCase
{
    public function testSupplementalRowsDoNotCreateDuplicateCoursesAndOrphansAreQuarantined(): void
    {
        $report = (new LegacySliceBAnalyzer())->analyze([
            'sn_escuela' => [
                [
                    'curso_id' => '3',
                    'curso_categoria' => '1',
                    'curso_titulo' => 'Curso de Títeres',
                    'curso_fecha_inicio' => '2026-01-10',
                    'curso_fecha_termino' => '2026-01-20',
                    'curso_display' => '1',
                    'url' => 'titeres',
                ],
            ],
            'sn_cursos' => [
                [
                    'id' => '3',
                    'title' => 'Curso de Títeres ampliado',
                    'description_text' => 'Información complementaria.',
                    'pdf_file' => '/docs/titeres.pdf',
                    'google_forms_link' => 'https://forms.example.test/titeres',
                    'display' => '1',
                ],
                [
                    'id' => '99',
                    'title' => 'Curso huérfano',
                    'display' => '1',
                ],
            ],
            'sn_escuela_img' => [
                [
                    'escuela_img_id' => '7',
                    'escuela_img_url' => '/images/titeres.jpg',
                    'escuela_img_alt' => 'Títeres',
                    'curso_id' => '3',
                    'escuela_img_display' => '1',
                ],
                [
                    'escuela_img_id' => '8',
                    'escuela_img_url' => '/images/orphan.jpg',
                    'curso_id' => '999',
                    'escuela_img_display' => '1',
                ],
            ],
            'sn_profesor' => [
                [
                    'profesor_id' => '12',
                    'profesor_nombre' => 'Ana Profesora',
                    'profesor_curso' => '3',
                    'profesor_display' => '1',
                ],
            ],
            'sn_categoria_escuela' => [
                ['id' => '1', 'titulo' => 'Nacional'],
            ],
        ], '/tmp/fixture.sql', str_repeat('b', 64), 1, 1);

        $courseMappings = array_filter(
            $report['mappings'],
            static fn (array $mapping): bool => $mapping['target_type'] === 'entry'
                && $mapping['target_key'] === 'cursos:titeres-3'
        );

        $this->assertCount(2, $courseMappings);
        $this->assertContains(LegacyMigrationCatalog::MAP_SUPPLEMENTAL, array_column($courseMappings, 'status'));
        $this->assertSame(2, $report['summary']['targets_planned']['cms_entries']); // one course plus one teacher
        $this->assertSame(1, $report['summary']['targets_planned']['cms_gallery_items']);
        $this->assertSame(1, $report['summary']['targets_planned']['cms_files']);
        $this->assertContains('external_link', array_column($report['mappings'], 'target_type'));
        $this->assertNotEmpty(array_filter(
            $report['quarantine'],
            static fn (array $item): bool => $item['error_class'] === 'fk_missing'
        ));
        $this->assertContains(LegacyMigrationCatalog::MAP_QUARANTINED, array_column($report['mappings'], 'status'));
    }
}
