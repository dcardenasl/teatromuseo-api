<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFailedJobsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'connection' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'queue' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'payload' => [
                'type' => 'TEXT',
            ],
            'exception' => [
                'type' => 'TEXT',
            ],
            'failed_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('failed_jobs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('failed_jobs', true);
    }
}
