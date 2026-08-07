<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\LegacyMigration\LegacySqlDumpReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Ad-hoc: compares two legacy dumps table by table and reports which primary
 * keys are new/removed/changed. Temporary tool, not part of the migration
 * engine's public surface.
 */
final class LegacyDiffDumps extends BaseCommand
{
    protected $group = 'Migration';
    protected $name = 'legacy:diff-dumps';
    protected $description = 'Diff two legacy SQL dumps by primary key per table.';

    private const TABLE_PK = [
        'sn_obra' => 'id_obra',
        'sn_noticias' => 'id_noticias',
        'sn_cursos' => 'id',
        'sn_escuela' => 'id',
        'sn_escuela_img' => 'id',
        'sn_compania' => 'id_compania',
        'sn_expo' => 'id',
        'sn_expo_img' => 'id',
        'sn_editorial' => 'id',
        'sn_prensa' => 'id',
        'sn_administracion' => 'id',
        'sn_museo' => 'id',
        'sn_funcionarios' => 'id',
        'sn_upa' => 'id',
        'sn_slider_cartelera' => 'id_slider',
        'sn_youtube' => 'id',
        'sn_profesor' => 'id',
        'sn_categoria_escuela' => 'id',
        'sn_slider' => 'id',
    ];

    public function run(array $params): int
    {
        $old = (string) CLI::getOption('old');
        $new = (string) CLI::getOption('new');
        if ($old === '' || $new === '') {
            CLI::error('Usage: php spark legacy:diff-dumps --old <path> --new <path>');

            return EXIT_ERROR;
        }

        $tables = array_keys(self::TABLE_PK);
        $oldReader = new LegacySqlDumpReader($old);
        $newReader = new LegacySqlDumpReader($new);
        $oldTables = $oldReader->rowsForTables($tables);
        $newTables = $newReader->rowsForTables($tables);

        $report = [];
        foreach ($tables as $table) {
            $pk = self::TABLE_PK[$table];
            $oldRows = $oldTables[$table] ?? [];
            $newRows = $newTables[$table] ?? [];

            $oldById = [];
            foreach ($oldRows as $row) {
                $oldById[(string) ($row[$pk] ?? '')] = $row;
            }
            $newById = [];
            foreach ($newRows as $row) {
                $newById[(string) ($row[$pk] ?? '')] = $row;
            }

            $addedIds = array_values(array_diff(array_keys($newById), array_keys($oldById)));
            $removedIds = array_values(array_diff(array_keys($oldById), array_keys($newById)));

            $changedIds = [];
            foreach ($newById as $id => $row) {
                if (! isset($oldById[$id])) {
                    continue;
                }
                if ($oldById[$id] !== $row) {
                    $changedIds[] = $id;
                }
            }

            sort($addedIds, SORT_NUMERIC);
            sort($removedIds, SORT_NUMERIC);
            sort($changedIds, SORT_NUMERIC);

            $report[$table] = [
                'old_count' => count($oldRows),
                'new_count' => count($newRows),
                'added_ids' => $addedIds,
                'removed_ids' => $removedIds,
                'changed_ids' => $changedIds,
                'added_rows' => array_map(static fn ($id) => $newById[$id], $addedIds),
            ];

            CLI::write(sprintf(
                '%s: old=%d new=%d added=%d removed=%d changed=%d',
                $table,
                count($oldRows),
                count($newRows),
                count($addedIds),
                count($removedIds),
                count($changedIds)
            ), 'yellow');
            if ($addedIds !== []) {
                CLI::write('  added ids: ' . implode(',', $addedIds));
            }
            if ($removedIds !== []) {
                CLI::write('  removed ids: ' . implode(',', $removedIds));
            }
            if ($changedIds !== []) {
                CLI::write('  changed ids: ' . implode(',', $changedIds));
            }
        }

        $outPath = WRITEPATH . 'logs/legacy-dump-diff.json';
        file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        CLI::write('Full report: ' . $outPath, 'green');

        return EXIT_SUCCESS;
    }
}
