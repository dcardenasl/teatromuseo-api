<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\LegacyMigration\LegacyAssetResolver;
use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use App\Libraries\LegacyMigration\LegacySliceAAnalyzer;
use App\Libraries\LegacyMigration\LegacySqlDumpReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Builds a compact, write-free migration report for a legacy slice.
 */
final class LegacyDryRun extends BaseCommand
{
    protected $group = 'Migration';
    protected $name = 'legacy:dry-run';
    protected $description = 'Analyze a legacy SQL slice without writing domain content.';
    protected $usage = 'php spark legacy:dry-run --slice A [--dump /path/dump.sql] [--asset-root /path] [--output /path]';
    protected $options = [
        '--slice' => 'Slice identifier. Currently supported: A.',
        '--dump' => 'Path to the legacy SQL dump. Defaults to the repository dump.',
        '--asset-root' => 'Optional root directory containing legacy public assets.',
        '--output' => 'Optional output directory for summary.json, mapping.json and CSV reports.',
        '--format' => 'Report format. Currently supported: json.',
    ];

    public function run(array $params): void
    {
        $slice = strtoupper((string) ($this->optionValue('slice') ?: 'A'));
        if ($slice !== 'A') {
            CLI::error("Unsupported slice '{$slice}'. Only Slice A is implemented.");

            return;
        }
        if (strtolower((string) ($this->optionValue('format') ?: 'json')) !== 'json') {
            CLI::error('Only --format=json is currently supported.');

            return;
        }

        $dumpPath = (string) ($this->optionValue('dump') ?: $this->defaultDumpPath());
        $assetRoot = $this->optionValue('asset-root');
        $outputDirectory = (string) ($this->optionValue('output') ?: WRITEPATH . 'logs/migration/' . date('Ymd-His') . '-slice-a');
        $runId = null;

        try {
            $reader = new LegacySqlDumpReader($dumpPath);
            $sourceHash = $reader->sourceHash();
            $tables = $reader->rowsForTables([
                'sn_compania',
                'sn_obra',
                'sn_slider_cartelera',
                'sn_youtube',
            ]);

            $repository = new LegacyMigrationRepository(Database::connect());
            $runId = $repository->createRun(
                pathinfo($dumpPath, PATHINFO_FILENAME),
                LegacyMigrationCatalog::MODE_DRY_RUN,
                $dumpPath,
                $sourceHash
            );

            $assetResolver = is_string($assetRoot) && trim($assetRoot) !== ''
                ? new LegacyAssetResolver($assetRoot)
                : null;
            $report = (new LegacySliceAAnalyzer($assetResolver))->analyze(
                $tables,
                $dumpPath,
                $sourceHash
            );

            $this->writeReport($outputDirectory, $report);
            $this->persistReport($repository, $runId, $sourceHash, $report);
            $repository->finishRun($runId, LegacyMigrationCatalog::RUN_COMPLETED, $report['summary']);

            $summary = $report['summary'];
            CLI::write('Legacy Slice A dry-run completed.', 'green');
            CLI::write('run_id=' . $runId, 'cyan');
            CLI::write('report=' . $outputDirectory, 'cyan');
            CLI::write(sprintf(
                'targets: cms_entries=%d events=%d occurrences=%d gallery_items=%d videos=%d issues=%d',
                $summary['targets_planned']['cms_entries'],
                $summary['targets_planned']['event_events'],
                $summary['targets_planned']['event_occurrences'],
                $summary['targets_planned']['cms_gallery_items'],
                $summary['slice_rows_selected']['videos'],
                $summary['issues']
            ));
        } catch (\Throwable $exception) {
            if ($runId !== null) {
                try {
                    (new LegacyMigrationRepository(Database::connect()))->finishRun(
                        $runId,
                        LegacyMigrationCatalog::RUN_FAILED,
                        [],
                        $exception->getMessage()
                    );
                } catch (\Throwable) {
                    // Preserve the original failure in the CLI output.
                }
            }

            CLI::error('Legacy Slice A dry-run failed: ' . $exception->getMessage());
        }
    }

    private function defaultDumpPath(): string
    {
        return dirname(APPPATH, 3) . '/docs/cte70303_wp440.sql';
    }

    private function optionValue(string $name): mixed
    {
        $value = CLI::getOption($name);
        if ($value !== null) {
            return $value;
        }

        foreach (CLI::getOptions() as $option => $optionValue) {
            $prefix = $name . '=';
            if (str_starts_with($option, $prefix)) {
                return substr($option, strlen($prefix));
            }
        }

        return null;
    }

    /** @param array{summary: array<string, mixed>, mappings: list<array<string, mixed>>, issues: list<array<string, mixed>>, quarantine: list<array<string, mixed>>, assets: list<array<string, mixed>>} $report */
    private function writeReport(string $directory, array $report): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create migration report directory '{$directory}'.");
        }

        $summary = json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $mapping = json_encode($report['mappings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $assets = json_encode($report['assets'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->writeFile($directory . '/summary.json', $summary);
        $this->writeFile($directory . '/mapping.json', $mapping);
        $this->writeFile($directory . '/assets.json', $assets);
        $this->writeCsv($directory . '/issues.csv', $report['issues'], [
            'legacy_table', 'legacy_id', 'issue_class', 'severity', 'field', 'original_value', 'applied_value', 'note',
        ]);
        $this->writeCsv($directory . '/quarantine.csv', $report['quarantine'], [
            'legacy_table', 'legacy_id', 'error_class', 'error_message',
        ]);
    }

    /** @param array{summary: array<string, mixed>, mappings: list<array<string, mixed>>, issues: list<array<string, mixed>>, quarantine: list<array<string, mixed>>, assets: list<array<string, mixed>>} $report */
    private function persistReport(LegacyMigrationRepository $repository, int $runId, string $sourceHash, array $report): void
    {
        foreach ($report['mappings'] as $mapping) {
            $repository->upsertMap(
                $runId,
                (string) $mapping['legacy_table'],
                (string) $mapping['legacy_id'],
                (string) $mapping['target_system'],
                (string) $mapping['target_type'],
                null,
                $sourceHash,
                (string) $mapping['status'],
                (string) $mapping['status'] === LegacyMigrationCatalog::MAP_DUPLICATE,
                'dry-run target_key=' . (string) $mapping['target_key']
            );
        }

        foreach ($report['issues'] as $issue) {
            $repository->recordIssue(
                $runId,
                (string) $issue['legacy_table'],
                (string) $issue['legacy_id'],
                (string) $issue['issue_class'],
                null,
                null,
                null,
                null,
                (string) ($issue['field'] ?? ''),
                $issue['original_value'] ?? null,
                $issue['applied_value'] ?? null,
                (string) ($issue['note'] ?? ''),
                (string) ($issue['severity'] ?? 'warning')
            );
        }
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents . PHP_EOL) === false) {
            throw new \RuntimeException("Unable to write migration report '{$path}'.");
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $headers
     */
    private function writeCsv(string $path, array $rows, array $headers): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open migration report '{$path}'.");
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $values[] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            fputcsv($handle, $values);
        }
        fclose($handle);
    }
}
