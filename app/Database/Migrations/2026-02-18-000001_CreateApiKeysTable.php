<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiKeysTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'key_prefix' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => false,
            ],
            'key_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
            'rate_limit_requests' => [
                'type'    => 'INT',
                'default' => 600,
                'null'    => false,
            ],
            'rate_limit_window' => [
                'type'    => 'INT',
                'default' => 60,
                'null'    => false,
            ],
            'user_rate_limit' => [
                'type'    => 'INT',
                'default' => 60,
                'null'    => false,
            ],
            'ip_rate_limit' => [
                'type'    => 'INT',
                'default' => 200,
                'null'    => false,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key_hash');
        $this->forge->createTable('api_keys');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_keys');
    }
}
