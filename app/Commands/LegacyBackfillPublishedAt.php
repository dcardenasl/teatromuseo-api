<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use App\Libraries\LegacyMigration\LegacySqlDumpReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\HTTP\CURLRequest;
use Config\Database;
use Config\Services;

/**
 * Ad-hoc: backfills cms_entries.published_at from a legacy table's own date
 * column, for entries already created before LEGACY-MAP-034 fixed
 * applyCmsEntry() to stop hardcoding published_at=null. Temporary tool, not
 * part of the migration engine's public surface.
 */
final class LegacyBackfillPublishedAt extends BaseCommand
{
    protected $group = 'Migration';
    protected $name = 'legacy:backfill-published-at';
    protected $description = 'Backfill cms_entries.published_at from a legacy table date column.';

    private const PK_COLUMN = [
        'sn_noticias' => 'id_noticias',
        'sn_editorial' => 'id',
        'sn_prensa' => 'id',
        'sn_administracion' => 'id',
    ];

    public function run(array $params): int
    {
        $table = (string) CLI::getOption('table');
        $dateColumn = (string) (CLI::getOption('date-column') ?: 'fecha');
        $dump = (string) CLI::getOption('dump');
        $cmsUrl = (string) (CLI::getOption('cms-url') ?: 'http://localhost:8190/api/v1');
        $tokenFile = (string) CLI::getOption('admin-token-file');
        $dryRun = CLI::getOption('confirm') === null;

        if ($table === '' || $dump === '' || ! isset(self::PK_COLUMN[$table])) {
            CLI::error('Usage: php spark legacy:backfill-published-at --table sn_noticias --dump <path> --admin-token-file <path> --confirm');

            return EXIT_ERROR;
        }
        $pkColumn = self::PK_COLUMN[$table];

        $token = '';
        if (! $dryRun) {
            if (! is_readable($tokenFile)) {
                CLI::error("Token file not readable: {$tokenFile}");

                return EXIT_ERROR;
            }
            $token = trim((string) file_get_contents($tokenFile));
        }

        $reader = new LegacySqlDumpReader($dump);
        $rows = $reader->rowsForTables([$table])[$table] ?? [];

        $db = Database::connect();
        $repository = new LegacyMigrationRepository($db);
        $http = Services::curlrequest([], null, null, false);

        $updated = 0;
        $skippedNoMap = 0;
        $skippedNoDate = 0;
        foreach ($rows as $row) {
            $legacyId = (string) ($row[$pkColumn] ?? '');
            $date = (string) ($row[$dateColumn] ?? '');
            if ($legacyId === '' || $date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $skippedNoDate++;
                continue;
            }

            $map = $repository->findMap($table, $legacyId, 'cms-domain', 'entry');
            $targetId = $map['target_id'] ?? null;
            if ($targetId === null) {
                $skippedNoMap++;
                continue;
            }

            $dateOnly = substr($date, 0, 10);
            // sn_noticias.fecha (and friends) sometimes holds the date of a future
            // activity the entry announces, not when it was written — publishing
            // in the future would hide the entry behind the CMS's
            // published_at <= NOW() visibility gate. Never publish in the future.
            if ($dateOnly > date('Y-m-d')) {
                $dateOnly = date('Y-m-d');
            }
            $publishedAt = $dateOnly . ' 00:00:00';
            CLI::write("entry {$targetId} ({$table}:{$legacyId}) -> published_at={$publishedAt}");

            if (! $dryRun) {
                $this->putPublishedAt($http, $cmsUrl, $token, (int) $targetId, $publishedAt);
            }
            $updated++;
        }

        CLI::write(sprintf(
            '%s%d entries %s (skipped: %d no-map, %d no-date)',
            $dryRun ? '[DRY RUN] ' : '',
            $updated,
            $dryRun ? 'would be updated' : 'updated',
            $skippedNoMap,
            $skippedNoDate
        ), 'green');

        return EXIT_SUCCESS;
    }

    private function putPublishedAt(CURLRequest $http, string $cmsUrl, string $token, int $entryId, string $publishedAt): void
    {
        $response = $http->request('PUT', rtrim($cmsUrl, '/') . '/cms/entries/' . $entryId, [
            'http_errors' => false,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => ['published_at' => $publishedAt],
        ]);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            CLI::error("  entry {$entryId} failed with HTTP {$status}: " . substr((string) $response->getBody(), 0, 300));
        }
    }
}
