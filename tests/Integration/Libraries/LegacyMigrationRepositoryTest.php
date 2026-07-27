<?php

declare(strict_types=1);

namespace Tests\Integration\Libraries;

use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacyMigrationRepository;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class LegacyMigrationRepositoryTest extends IntegrationTestCase
{
    private LegacyMigrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LegacyMigrationRepository($this->db);
    }

    public function testMapIsIdempotentAndIssuesAndQuarantineKeepSourceContext(): void
    {
        $runId = $this->repository->createRun(
            'cte70303_wp440',
            LegacyMigrationCatalog::MODE_DRY_RUN,
            'docs/cte70303_wp440.sql',
            hash('sha256', 'fixture')
        );

        $mapId = $this->repository->upsertMap(
            $runId,
            'sn_obra',
            '1',
            LegacyMigrationCatalog::TARGET_CMS,
            'entry',
            '42',
            hash('sha256', 'row-1')
        );
        $sameMapId = $this->repository->upsertMap(
            $runId,
            'sn_obra',
            '1',
            LegacyMigrationCatalog::TARGET_CMS,
            'entry',
            '42',
            hash('sha256', 'row-1')
        );

        $this->assertSame($mapId, $sameMapId);
        $this->assertSame(1, $this->db->table('legacy_migration_map')->countAllResults());

        $issueId = $this->repository->recordIssue(
            $runId,
            'sn_obra',
            '1',
            'invalid_date',
            $mapId,
            LegacyMigrationCatalog::TARGET_CMS,
            'entry',
            '42',
            'fecha_obra',
            '0000-00-00',
            null,
            'Legacy zero date converted to null.'
        );
        $quarantineId = $this->repository->quarantine(
            $runId,
            'sn_slider_cartelera',
            '99',
            ['id_slider' => 99, 'id_obra' => 0],
            'fk_missing',
            'The source work could not be resolved.',
            null
        );

        $this->assertGreaterThan(0, $issueId);
        $this->assertGreaterThan(0, $quarantineId);
        $this->assertSame('0000-00-00', $this->db->table('legacy_migration_issues')->where('id', $issueId)->get()->getRowArray()['original_value']);
        $this->assertSame(
            ['id_obra' => 0, 'id_slider' => 99],
            json_decode((string) $this->db->table('legacy_migration_quarantine')->where('id', $quarantineId)->get()->getRowArray()['raw_row'], true)
        );

        $this->repository->finishRun($runId, LegacyMigrationCatalog::RUN_COMPLETED, ['rows' => 1]);
        $run = $this->db->table('legacy_migration_runs')->where('id', $runId)->get()->getRowArray();
        $this->assertSame('completed', $run['status']);
        $this->assertNotNull($run['completed_at']);
    }
}
