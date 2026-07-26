<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTranslationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'translatable_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'translatable_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => false,
            ],
            'field' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'value' => [
                'type' => 'MEDIUMTEXT',
                'null' => false,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['translatable_type', 'translatable_id', 'locale', 'field'],
            'uq_translation'
        );
        $this->forge->addKey(
            ['translatable_type', 'translatable_id'],
            false,
            false,
            'idx_translatable'
        );
        $this->forge->createTable('translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('translations');
    }
}
