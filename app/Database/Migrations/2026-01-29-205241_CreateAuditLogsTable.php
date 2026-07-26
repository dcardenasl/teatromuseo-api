<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'entity_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'old_values' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'new_values' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
            ],
            'user_agent' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'entity_type', 'entity_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs');
    }
}
