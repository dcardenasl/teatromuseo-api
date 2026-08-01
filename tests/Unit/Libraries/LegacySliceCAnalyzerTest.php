<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacySliceCAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacySliceCAnalyzerTest extends TestCase
{
    public function testSliceCPlansEntriesAndBlocksCorrectly(): void
    {
        $report = (new LegacySliceCAnalyzer())->analyze([
            'sn_expo' => [
                ['id' => '1', 'autor' => 'Sebastian', 'titulo' => 'Expo 1', 'descripcion' => 'Desc 1', 'fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-01-10', 'url' => 'expo-1', 'display' => '1'],
                ['id' => '2', 'autor' => 'Victor', 'titulo' => 'Expo 2', 'descripcion' => 'Desc 2', 'fecha_desde' => 'invalid-date', 'fecha_hasta' => '2020-02-10', 'url' => 'expo-2', 'display' => '1'],
            ],
            'sn_expo_img' => [
                ['id' => '10', 'expo_id' => '1', 'img' => '/images/expo1_1.jpg', 'display' => '1'],
                ['id' => '11', 'expo_id' => '1', 'img' => '/images/expo1_2.jpg', 'display' => '1'],
            ],
            'sn_noticias' => [
                ['id_noticias' => '5', 'titulo' => 'Noticia 1', 'lead' => 'Lead 1', 'cuerpo' => 'Cuerpo 1', 'disp_noticias' => '1', 'fecha' => '2020-05-01', 'foto' => '/images/news1.jpg', 'url' => 'noticia-1'],
            ],
            'sn_editorial' => [
                ['id' => '20', 'titulo' => 'Editorial 1', 'descripcion' => 'Desc Ed', 'archivo' => '/docs/ed1.pdf', 'display' => '1', 'fecha' => '2020-06-01', 'foto' => '/images/ed1.jpg', 'link' => '', 'url' => 'ed-1'],
            ],
            'sn_prensa' => [
                ['id' => '30', 'titulo' => 'Prensa 1', 'descripcion' => 'Desc Pr', 'archivo' => '/docs/pr1.pdf', 'display' => '1'],
            ],
            'sn_administracion' => [
                ['id' => '40', 'titulo' => 'Admin 1', 'descripcion' => 'Desc Ad', 'archivo' => '/docs/ad1.pdf', 'display' => '1'],
            ],
            'sn_upa' => [
                ['id_upa' => '1', 'titulo' => 'Upa 1', 'cuerpo' => 'Cuerpo Upa', 'pie' => 'Pie Upa'],
            ],
            'sn_funcionarios' => [
                ['id' => '50', 'nombre' => 'Víctor Q', 'profesion' => 'Payaso', 'cargo' => 'Director', 'correo' => 'victor@test.cl', 'foto1' => '/images/victor.png', 'foto2' => '', 'posicion' => '1', 'display' => '1'],
            ],
            'sn_museo' => [
                ['id' => '1', 'titulo' => 'El museo esta situado...', 'imagen' => '/images/museo.jpg'],
            ],
        ], '/tmp/fixture.sql', str_repeat('a', 64), 10, 10, 10, 10);

        $this->assertSame(2, $report['summary']['slice_rows_selected']['exposiciones']);
        $this->assertSame(1, $report['summary']['slice_rows_selected']['noticias']);
        $this->assertSame(3, $report['summary']['slice_rows_selected']['publicaciones']);
        $this->assertSame(1, $report['summary']['slice_rows_selected']['festivales']);
        $this->assertSame(1, $report['summary']['slice_rows_selected']['personas']);
        $this->assertSame(1, $report['summary']['slice_rows_selected']['museo']);

        // Check planned targets:
        // Entries: 2 expos + 1 news + 3 pubs + 1 festival + 1 persona = 8 entries planned
        $this->assertSame(8, $report['summary']['targets_planned']['cms_entries']);

        // Blocks: 1 gallery + 1 gallery item + 1 page block (sn_museo) = 3 block mappings planned
        $this->assertSame(3, $report['summary']['targets_planned']['cms_blocks']);

        // Find issues: date issue warning for expo 2
        $this->assertNotEmpty(array_filter($report['issues'], static fn (array $issue): bool => $issue['issue_class'] === 'invalid_date'));
    }

    public function testAnimateEditionIsPlannedAsAFestivalNotAGenericWork(): void
    {
        $report = (new LegacySliceCAnalyzer())->analyze([
            'sn_obra' => [
                [
                    'id_obra' => '692',
                    'titulo_obra' => 'Animate',
                    'fecha_obra' => '2024-11-02',
                    'foto_obra' => '/images/cartelera/full/2-nov.png',
                    'descripcion_corta_obra' => 'IX Encuentro Internacional de Títeres Animate',
                    'descripcion_larga_obra' => 'IX Encuentro Internacional de Títeres Animate',
                    'display' => '1',
                    'url' => 'animate',
                ],
                // A regular show sharing sn_obra but not the festival's url must be ignored here.
                ['id_obra' => '1', 'titulo_obra' => 'Otra obra', 'url' => 'otra-obra', 'display' => '1'],
            ],
        ], '/tmp/fixture.sql', str_repeat('a', 64));

        $this->assertSame(1, $report['summary']['slice_rows_selected']['festivales']);
        $animateMappings = array_values(array_filter($report['mappings'], static fn (array $m): bool => $m['legacy_table'] === 'sn_obra'));
        $this->assertCount(1, $animateMappings);
        $this->assertSame('692', $animateMappings[0]['legacy_id']);
        $this->assertSame('festivales:animate-2024', $animateMappings[0]['target_key']);
    }
}
