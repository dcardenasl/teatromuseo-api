<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTokenBlacklistTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'token_jti' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_jti');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('token_blacklist');
    }

    public function down(): void
    {
        $this->forge->dropTable('token_blacklist');
    }
}
