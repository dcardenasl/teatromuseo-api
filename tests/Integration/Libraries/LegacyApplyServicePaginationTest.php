<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\LegacyMigration\LegacyApplyService;
use App\Libraries\LegacyMigration\LegacyDomainClientInterface;
use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class LegacyApplyServicePaginationTest extends IntegrationTestCase
{
    public function testFindCmsEntryWalksEveryPageInsteadOfTrustingTheFirstOne(): void
    {
        // cms-domain clamps per_page to 100 server-side no matter what's requested — with a
        // real collection past that size, an entry that only exists on a later page must still
        // be found (and reused), not silently missed and duplicated. Simulate a "festivales"
        // collection with 101 existing entries, where the one this run cares about
        // ("upa-chalupa-2019") sits on page 2.
        $hash = hash('sha256', 'legacy-pagination-fixture');
        $tables = ['sn_upa' => [['id_upa' => '1', 'titulo' => 'Festival Upa Chalupa', 'pie' => '', 'cuerpo' => '']]];

        $client = new PaginatedEntriesFakeClient();
        $repository = new LegacyMigrationRepository($this->db);
        $service = new LegacyApplyService($repository, $client, $client, $client, null, $hash);
        $runId = $repository->createRun('legacy-pagination-fixture', LegacyMigrationCatalog::MODE_APPLY, '/tmp/pagination-fixture.sql', $hash);

        $summary = $service->apply('C', $tables, '/tmp/pagination-fixture.sql', $runId);

        $this->assertSame(0, $summary['created']['cms_entries']);
        $this->assertSame(1, $summary['reused']['cms_entries']);
        $this->assertGreaterThanOrEqual(2, $client->entriesPageRequests, 'expected findCmsEntry() to request more than one page');
    }
}

/**
 * Simulates a paginated /cms/entries listing split across 2 pages of 100,
 * where the entry findCmsEntry() needs is on page 2 — everything else
 * behaves like the minimal fixtures elsewhere in this suite.
 */
final class PaginatedEntriesFakeClient implements LegacyDomainClientInterface
{
    public int $entriesPageRequests = 0;
    private int $nextId = 500;

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): array
    {
        if ($path === '/cms/entries') {
            $this->entriesPageRequests++;
            $page = (int) ($query['page'] ?? 1);
            if ($page === 1) {
                $filler = [];
                for ($i = 1; $i <= 100; $i++) {
                    $filler[] = ['id' => $i, 'collection_id' => 6, 'slug' => 'filler-' . $i, 'translations' => []];
                }

                return ['data' => ['items' => $filler], 'meta' => ['last_page' => 2]];
            }

            return ['data' => ['items' => [
                ['id' => 200, 'collection_id' => 6, 'slug' => 'upa-chalupa-2019', 'translations' => []],
            ]], 'meta' => ['last_page' => 2]];
        }

        return match ($path) {
            '/cms/collections' => ['data' => ['items' => [['id' => 6, 'collection_key' => 'festivales']]]],
            '/cms/languages' => ['data' => ['items' => [['id' => 1, 'code' => 'es']]]],
            '/cms/block-types' => ['data' => ['items' => []]],
            default => throw new \RuntimeException("Unexpected pagination-test GET {$path}"),
        };
    }

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload = []): array
    {
        return ['data' => ['id' => $this->nextId++]];
    }

    /** @param array<string, mixed> $fields */
    public function upload(string $path, string $filePath, string $filename, array $fields = []): array
    {
        throw new \RuntimeException('The fixture intentionally has no assets.');
    }
}
