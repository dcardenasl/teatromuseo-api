<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddControlColumnsToAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('audit_logs', [
            'result' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'success',
                'after' => 'user_agent',
            ],
            'severity' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'info',
                'after' => 'result',
            ],
            'request_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'severity',
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true,
                'after' => 'request_id',
            ],
        ]);

        $this->forge->addKey(['action', 'created_at'], false, false, 'idx_audit_action_created_at');
        $this->forge->addKey(['severity', 'created_at'], false, false, 'idx_audit_severity_created_at');
        $this->forge->addKey(['result', 'created_at'], false, false, 'idx_audit_result_created_at');
        $this->forge->addKey('request_id', false, false, 'idx_audit_request_id');
        $this->forge->processIndexes('audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropKey('audit_logs', 'idx_audit_action_created_at');
        $this->forge->dropKey('audit_logs', 'idx_audit_severity_created_at');
        $this->forge->dropKey('audit_logs', 'idx_audit_result_created_at');
        $this->forge->dropKey('audit_logs', 'idx_audit_request_id');

        $this->forge->dropColumn('audit_logs', ['result', 'severity', 'request_id', 'metadata']);
    }
}
