<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMetricsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'metric_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'metric_value' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'tags' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['metric_name', 'created_at']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('metrics');
    }

    public function down(): void
    {
        $this->forge->dropTable('metrics');
    }
}
