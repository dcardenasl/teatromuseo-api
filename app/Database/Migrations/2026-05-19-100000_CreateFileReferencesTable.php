<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFileReferencesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'file_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'resource_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'default',
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['resource_type', 'resource_id', 'role'], 'uq_file_ref_resource_role');
        $this->forge->addKey('file_id', false, false, 'idx_file_references_file_id');

        $this->forge->createTable('file_references', true);

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query(
                'ALTER TABLE file_references ADD CONSTRAINT fk_file_refs_file '
                . 'FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE RESTRICT'
            );
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('file_references', true);
    }
}
