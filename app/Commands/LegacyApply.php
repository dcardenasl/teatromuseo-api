<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\LegacyMigration\LegacyApplyService;
use App\Libraries\LegacyMigration\LegacyAssetResolver;
use App\Libraries\LegacyMigration\LegacyDomainClientInterface;
use App\Libraries\LegacyMigration\LegacyHttpDomainClient;
use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use App\Libraries\LegacyMigration\LegacySqlDumpReader;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Applies one analyzed legacy slice through cms-domain/event-domain APIs.
 */
final class LegacyApply extends BaseCommand
{
    protected $group = 'Migration';
    protected $name = 'legacy:apply';
    protected $description = 'Apply a legacy slice through CMS/Event APIs with idempotent mapping.';
    protected $usage = 'php spark legacy:apply --slice A --confirm --admin-token-file /path/token.txt [--dump /path/dump.sql] [--asset-root /path]';
    protected $options = [
        '--slice' => 'Slice identifier: A or B.',
        '--confirm' => 'Required explicit confirmation before writing domain content.',
        '--admin-token' => 'JWT for a superadmin account. Never store it in source control.',
        '--admin-token-file' => 'Readable file containing a JWT; safer than exposing it in process arguments.',
        '--dump' => 'Path to the legacy SQL dump. Defaults to the repository dump.',
        '--asset-root' => 'Optional root directory containing legacy public assets.',
        '--cms-url' => 'CMS API base URL. Defaults to http://localhost:8190/api/v1.',
        '--event-url' => 'Event API base URL. Defaults to http://localhost:8193/api/v1.',
        '--hub-url' => 'Hub API base URL. Defaults to http://localhost:8180/api/v1.',
    ];

    public function run(array $params): int
    {
        $slice = strtoupper((string) ($this->optionValue('slice') ?: 'A'));
        if (! in_array($slice, ['A', 'B'], true)) {
            CLI::error("Unsupported slice '{$slice}'. Supported slices: A, B.");
            return EXIT_ERROR;
        }
        if ($this->optionValue('confirm') === null) {
            CLI::error('Apply blocked: add --confirm explicitly to authorize domain writes.');
            return EXIT_ERROR;
        }

        $tokenFile = trim((string) ($this->optionValue('admin-token-file') ?: ''));
        if ($tokenFile !== '') {
            if (! is_readable($tokenFile)) {
                CLI::error("Apply blocked: token file is not readable: {$tokenFile}");
                return EXIT_ERROR;
            }
            $tokenContents = file_get_contents($tokenFile);
            $token = $tokenContents === false ? '' : trim($tokenContents);
        } else {
            $token = trim((string) ($this->optionValue('admin-token') ?: getenv('LEGACY_ADMIN_TOKEN') ?: ''));
        }
        if ($token === '') {
            CLI::error('Apply blocked: provide --admin-token or LEGACY_ADMIN_TOKEN.');
            return EXIT_ERROR;
        }

        $dumpPath = (string) ($this->optionValue('dump') ?: dirname(APPPATH, 3) . '/docs/cte70303_wp440.sql');
        $assetRoot = $this->optionValue('asset-root');
        $cmsUrl = (string) ($this->optionValue('cms-url') ?: 'http://localhost:8190/api/v1');
        $eventUrl = (string) ($this->optionValue('event-url') ?: 'http://localhost:8193/api/v1');
        $hubUrl = (string) ($this->optionValue('hub-url') ?: 'http://localhost:8180/api/v1');
        $runId = null;

        try {
            $reader = new LegacySqlDumpReader($dumpPath);
            $sourceHash = $reader->sourceHash();
            $tables = $reader->rowsForTables($slice === 'B'
                ? ['sn_escuela', 'sn_cursos', 'sn_escuela_img', 'sn_profesor', 'sn_categoria_escuela']
                : ['sn_compania', 'sn_obra', 'sn_slider_cartelera', 'sn_youtube']);
            $repository = new LegacyMigrationRepository(Database::connect());
            $runId = $repository->createRun(pathinfo($dumpPath, PATHINFO_FILENAME), LegacyMigrationCatalog::MODE_APPLY, $dumpPath, $sourceHash);
            $resolver = is_string($assetRoot) && trim($assetRoot) !== '' ? new LegacyAssetResolver($assetRoot) : null;

            $service = new LegacyApplyService(
                $repository,
                $this->client($cmsUrl, $token),
                $this->client($eventUrl, $token),
                $this->client($hubUrl, $token),
                $resolver,
                $sourceHash
            );
            $summary = $service->apply($slice, $tables, $dumpPath, $runId);
            $repository->finishRun($runId, LegacyMigrationCatalog::RUN_COMPLETED, $summary);

            CLI::write("Legacy Slice {$slice} apply completed.", 'green');
            CLI::write('run_id=' . $runId, 'cyan');
            CLI::write(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return EXIT_SUCCESS;
        } catch (\Throwable $exception) {
            if ($runId !== null) {
                try {
                    (new LegacyMigrationRepository(Database::connect()))->finishRun($runId, LegacyMigrationCatalog::RUN_FAILED, [], $exception->getMessage());
                } catch (\Throwable) {
                    // Preserve the original failure in CLI output.
                }
            }
            CLI::error("Legacy Slice {$slice} apply failed: " . $exception->getMessage());
            return EXIT_ERROR;
        }
    }

    private function client(string $baseUrl, string $token): LegacyDomainClientInterface
    {
        return new LegacyHttpDomainClient($baseUrl, $token);
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
}
