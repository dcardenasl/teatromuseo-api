<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRequestLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'method' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
            ],
            'uri' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
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
            'response_code' => [
                'type' => 'SMALLINT',
                'unsigned' => true,
            ],
            'response_time' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('request_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('request_logs');
    }
}
