<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use App\Libraries\LegacyMigration\LegacySliceDAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacySliceDAnalyzerTest extends TestCase
{
    private const TABLES = [
        'sn_contact_status' => [
            ['id' => '1', 'title' => 'PENDIENTE'],
            ['id' => '2', 'title' => 'COMPLETADA'],
        ],
    ];

    public function testEveryValidRowIsPlannedAsAFormSubmission(): void
    {
        $report = (new LegacySliceDAnalyzer())->analyze([
            'sn_contact_message' => [
                ['id' => '16', 'date_send' => '2024-07-11 16:54:23', 'name_contact' => 'Silvana Vargas', 'email_address' => 'svargas@example.cl', 'phone_number' => '949019332', 'message_text' => 'Hola', 'status_id' => '2', 'ip_address' => null, 'user_agent' => null],
                ['id' => '17', 'date_send' => '2024-07-18 09:27:17', 'name_contact' => 'Daniele Lupi', 'email_address' => 'daniele@example.it', 'phone_number' => '2147483647', 'message_text' => 'Buen dia', 'status_id' => '2', 'ip_address' => null, 'user_agent' => null],
            ],
        ] + self::TABLES, '/tmp/fixture.sql', str_repeat('a', 64));

        $this->assertSame(2, $report['summary']['slice_rows_selected']['contact_messages']);
        $this->assertSame(2, $report['summary']['targets_planned']['cms_form_submissions']);
        $this->assertSame(0, $report['summary']['issues']);
        $this->assertSame(['sn_contact_message', 'sn_contact_message'], array_column($report['mappings'], 'legacy_table'));
        $this->assertSame(LegacyMigrationCatalog::MAP_PLANNED, $report['mappings'][0]['status']);
        $this->assertSame('form_submission', $report['mappings'][0]['target_type']);
    }

    public function testMissingIdentityAndInvalidDateAreReportedAsIssuesNotDropped(): void
    {
        $report = (new LegacySliceDAnalyzer())->analyze([
            'sn_contact_message' => [
                ['id' => '', 'date_send' => '2024-07-11 16:54:23', 'name_contact' => 'x', 'email_address' => 'a@b.cl', 'phone_number' => '1', 'message_text' => 'msg', 'status_id' => '2'],
                ['id' => '18', 'date_send' => '0000-00-00 00:00:00', 'name_contact' => 'y', 'email_address' => 'c@d.cl', 'phone_number' => '2', 'message_text' => 'msg', 'status_id' => '2'],
            ],
        ] + self::TABLES, '/tmp/fixture.sql', str_repeat('a', 64));

        // The row with no legacy id is skipped entirely (nothing to map back to); the
        // row with a bad date is still planned — apply() falls back to import time.
        $this->assertSame(1, $report['summary']['slice_rows_selected']['contact_messages']);
        $this->assertSame(2, $report['summary']['issues']);
        $this->assertNotEmpty(array_filter($report['issues'], static fn (array $issue): bool => $issue['issue_class'] === 'missing_identity'));
        $this->assertNotEmpty(array_filter($report['issues'], static fn (array $issue): bool => $issue['issue_class'] === 'invalid_date'));
    }

    public function testUnknownStatusIdIsFlaggedButStillPlanned(): void
    {
        $report = (new LegacySliceDAnalyzer())->analyze([
            'sn_contact_message' => [
                ['id' => '19', 'date_send' => '2024-07-11 16:54:23', 'name_contact' => 'x', 'email_address' => 'a@b.cl', 'phone_number' => '1', 'message_text' => 'msg', 'status_id' => '99'],
            ],
        ] + self::TABLES, '/tmp/fixture.sql', str_repeat('a', 64));

        $this->assertSame(1, $report['summary']['slice_rows_selected']['contact_messages']);
        $this->assertNotEmpty(array_filter($report['issues'], static fn (array $issue): bool => $issue['issue_class'] === 'fk_missing'));
    }
}
