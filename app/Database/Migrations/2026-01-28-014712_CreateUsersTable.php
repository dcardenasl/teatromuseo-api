<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
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
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'oauth_provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'oauth_provider_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'avatar_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending_approval',
                'null'       => false,
            ],
            'email_verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verification_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'verification_token_expires' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'approved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'approved_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'invited_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'invited_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('email_verification_token');
        $this->forge->createTable('users');

        // Add FULLTEXT index for search functionality
        try {
            $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email, first_name, last_name)');
        } catch (\Throwable $e) {
            // Ignore if engine doesn't support FULLTEXT (like in tests with SQLite)
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('users');
    }
}
