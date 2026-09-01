<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Creates the hub-owned control plane for the legacy content migration.
 *
 * These tables deliberately live outside cms-domain and event-domain. They
 * record the source row and the result produced in one or more target
 * domains, without introducing a database-level dependency between those
 * domains. The ETL can therefore be retried, audited and quarantined without
 * adding migration concerns to editorial or operational models.
 */
final class CreateLegacyMigrationControlTables extends Migration
{
    public function up(): void
    {
        $this->createRunsTable();
        $this->createMapTable();
        $this->createIssuesTable();
        $this->createQuarantineTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('legacy_migration_quarantine', true);
        $this->forge->dropTable('legacy_migration_issues', true);
        $this->forge->dropTable('legacy_migration_map', true);
        $this->forge->dropTable('legacy_migration_runs', true);
    }

    private function createRunsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'run_uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'source_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
                'null'       => false,
            ],
            'source_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'source_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'dry_run',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'running',
            ],
            'summary' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'started_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('run_uuid', 'uq_legacy_migration_run_uuid');
        $this->forge->addKey(['source_name', 'source_hash'], false, false, 'idx_legacy_migration_run_source');
        $this->forge->addKey(['status', 'started_at'], false, false, 'idx_legacy_migration_run_status');
        $this->forge->createTable('legacy_migration_runs');
    }

    private function createMapTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'run_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'legacy_table' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'legacy_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'source_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'target_system' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 96,
                'null'       => false,
            ],
            'target_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'mapped',
            ],
            'is_duplicate' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('run_id', 'legacy_migration_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addUniqueKey(
            ['legacy_table', 'legacy_id', 'target_system', 'target_type'],
            'uq_legacy_migration_target'
        );
        $this->forge->addKey(['legacy_table', 'legacy_id'], false, false, 'idx_legacy_migration_source');
        $this->forge->addKey(['target_system', 'target_type', 'target_id'], false, false, 'idx_legacy_migration_target_lookup');
        $this->forge->createTable('legacy_migration_map');
    }

    private function createIssuesTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'run_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'map_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'legacy_table' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'legacy_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'target_system' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 96,
                'null'       => true,
            ],
            'target_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'issue_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 48,
                'null'       => false,
            ],
            'severity' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'warning',
            ],
            'field' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'original_value' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'applied_value' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'resolution' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'pending',
            ],
            'resolved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('run_id', 'legacy_migration_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('map_id', 'legacy_migration_map', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addKey(['legacy_table', 'legacy_id'], false, false, 'idx_legacy_migration_issue_source');
        $this->forge->addKey(['issue_class', 'resolution'], false, false, 'idx_legacy_migration_issue_resolution');
        $this->forge->createTable('legacy_migration_issues');
    }

    private function createQuarantineTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'run_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'map_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'legacy_table' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'legacy_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'raw_row' => [
                'type' => 'JSON',
                'null' => false,
            ],
            'error_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 48,
                'null'       => false,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'resolution' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'pending',
            ],
            'resolved_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'resolved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('run_id', 'legacy_migration_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('map_id', 'legacy_migration_map', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addKey(['legacy_table', 'resolution'], false, false, 'idx_legacy_migration_quarantine_resolution');
        $this->forge->createTable('legacy_migration_quarantine');
    }
}
