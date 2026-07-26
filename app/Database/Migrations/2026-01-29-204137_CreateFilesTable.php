<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFilesTable extends Migration
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
            ],
            'original_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'stored_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'size' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'storage_driver' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'url' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'uploaded_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('files');
    }

    public function down(): void
    {
        $this->forge->dropTable('files');
    }
}
