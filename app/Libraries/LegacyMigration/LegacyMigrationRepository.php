<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

use CodeIgniter\Database\BaseConnection;

/**
 * Persistence adapter for the hub-owned migration control plane.
 *
 * The adapter owns idempotency for source-to-target mappings. ETL steps can
 * therefore be small and stateless: they resolve a legacy row, call
 * upsertMap(), and record either a non-destructive issue or quarantine entry.
 */
final class LegacyMigrationRepository
{
    /** @param BaseConnection<object, object> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function createRun(
        string $sourceName,
        string $mode = LegacyMigrationCatalog::MODE_DRY_RUN,
        ?string $sourcePath = null,
        ?string $sourceHash = null
    ): int {
        $sourceName = trim($sourceName);
        if ($sourceName === '') {
            throw new \InvalidArgumentException('Migration source name cannot be empty.');
        }
        if (! LegacyMigrationCatalog::isRunMode($mode)) {
            throw new \InvalidArgumentException("Unsupported migration mode '{$mode}'.");
        }
        if ($sourceHash !== null && ! preg_match('/^[a-f0-9]{64}$/i', $sourceHash)) {
            throw new \InvalidArgumentException('Migration source hash must be a SHA-256 hexadecimal string.');
        }

        $inserted = $this->db->table('legacy_migration_runs')->insert([
            'run_uuid'    => $this->uuid4(),
            'source_name' => $sourceName,
            'source_path' => $sourcePath,
            'source_hash' => $sourceHash,
            'mode'        => $mode,
            'status'      => LegacyMigrationCatalog::RUN_RUNNING,
        ]);
        if (! $inserted) {
            throw new \RuntimeException('Unable to create legacy migration run.');
        }

        return (int) $this->db->insertID();
    }

    /**
     * @param string|null $targetId Domain IDs may be numeric IDs or UUIDs.
     */
    public function upsertMap(
        int $runId,
        string $legacyTable,
        string $legacyId,
        string $targetSystem,
        string $targetType,
        ?string $targetId,
        ?string $sourceHash = null,
        string $status = LegacyMigrationCatalog::MAP_MAPPED,
        bool $isDuplicate = false,
        ?string $note = null
    ): int {
        $this->assertSourceIdentity($legacyTable, $legacyId);
        $this->assertTargetIdentity($targetSystem, $targetType);
        if (! LegacyMigrationCatalog::isMapStatus($status)) {
            throw new \InvalidArgumentException("Unsupported migration map status '{$status}'.");
        }

        $builder = $this->db->table('legacy_migration_map');
        $result = $builder
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->where('target_system', $targetSystem)
            ->where('target_type', $targetType)
            ->get();
        if ($result === false) {
            throw new \RuntimeException('Unable to query legacy migration map.');
        }
        $existing = $result->getRowArray();

        $payload = [
            'run_id'       => $runId,
            'legacy_table' => $legacyTable,
            'legacy_id'    => $legacyId,
            'source_hash'  => $sourceHash,
            'target_system' => $targetSystem,
            'target_type'  => $targetType,
            'target_id'    => $targetId,
            'status'       => $status,
            'is_duplicate' => $isDuplicate ? 1 : 0,
            'note'         => $note,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($existing !== null) {
            $updated = $this->db->table('legacy_migration_map')
                ->where('id', (int) $existing['id'])
                ->update($payload);
            if (! $updated) {
                throw new \RuntimeException('Unable to update legacy migration map row.');
            }

            return (int) $existing['id'];
        }

        $inserted = $this->db->table('legacy_migration_map')->insert($payload);
        if (! $inserted) {
            throw new \RuntimeException('Unable to insert legacy migration map row.');
        }

        return (int) $this->db->insertID();
    }

    public function recordIssue(
        int $runId,
        string $legacyTable,
        string $legacyId,
        string $issueClass,
        ?int $mapId = null,
        ?string $targetSystem = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $field = null,
        mixed $originalValue = null,
        mixed $appliedValue = null,
        ?string $note = null,
        string $severity = 'warning'
    ): int {
        $this->assertSourceIdentity($legacyTable, $legacyId);
        $issueClass = trim($issueClass);
        if ($issueClass === '') {
            throw new \InvalidArgumentException('Migration issue class cannot be empty.');
        }
        if (! in_array($severity, ['info', 'warning', 'error'], true)) {
            throw new \InvalidArgumentException("Unsupported migration issue severity '{$severity}'.");
        }

        $inserted = $this->db->table('legacy_migration_issues')->insert([
            'run_id'         => $runId,
            'map_id'         => $mapId,
            'legacy_table'   => $legacyTable,
            'legacy_id'      => $legacyId,
            'target_system'  => $targetSystem,
            'target_type'    => $targetType,
            'target_id'      => $targetId,
            'issue_class'    => $issueClass,
            'severity'       => $severity,
            'field'          => $field,
            'original_value' => $this->stringify($originalValue),
            'applied_value'  => $this->stringify($appliedValue),
            'note'           => $note,
        ]);
        if (! $inserted) {
            throw new \RuntimeException('Unable to record legacy migration issue.');
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $rawRow */
    public function quarantine(
        int $runId,
        string $legacyTable,
        string $legacyId,
        array $rawRow,
        string $errorClass,
        string $errorMessage,
        ?int $mapId = null
    ): int {
        $this->assertSourceIdentity($legacyTable, $legacyId);
        $errorClass = trim($errorClass);
        $errorMessage = trim($errorMessage);
        if ($errorClass === '' || $errorMessage === '') {
            throw new \InvalidArgumentException('Quarantine class and message are required.');
        }

        $inserted = $this->db->table('legacy_migration_quarantine')->insert([
            'run_id'         => $runId,
            'map_id'         => $mapId,
            'legacy_table'   => $legacyTable,
            'legacy_id'      => $legacyId,
            'raw_row'        => json_encode($rawRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'error_class'    => $errorClass,
            'error_message'  => $errorMessage,
            'resolution'     => LegacyMigrationCatalog::RESOLUTION_PENDING,
        ]);
        if (! $inserted) {
            throw new \RuntimeException('Unable to quarantine legacy migration row.');
        }

        return (int) $this->db->insertID();
    }

    /** @param array<string, mixed> $summary */
    public function finishRun(
        int $runId,
        string $status,
        array $summary = [],
        ?string $errorMessage = null
    ): void {
        if (! in_array($status, [
            LegacyMigrationCatalog::RUN_COMPLETED,
            LegacyMigrationCatalog::RUN_FAILED,
            LegacyMigrationCatalog::RUN_CANCELLED,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported terminal migration run status '{$status}'.");
        }

        $updated = $this->db->table('legacy_migration_runs')
            ->where('id', $runId)
            ->update([
                'status'        => $status,
                'summary'       => $summary === [] ? null : json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'error_message' => $errorMessage,
                'completed_at'  => date('Y-m-d H:i:s'),
            ]);
        if (! $updated) {
            throw new \RuntimeException("Unable to finish legacy migration run {$runId}.");
        }
    }

    private function assertSourceIdentity(string $legacyTable, string $legacyId): void
    {
        if (trim($legacyTable) === '' || trim($legacyId) === '') {
            throw new \InvalidArgumentException('Legacy table and ID are required for migration traceability.');
        }
    }

    private function assertTargetIdentity(string $targetSystem, string $targetType): void
    {
        if (trim($targetSystem) === '' || trim($targetType) === '') {
            throw new \InvalidArgumentException('Target system and type are required for migration mapping.');
        }
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
