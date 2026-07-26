<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolePermissionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'role_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'permission_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);

        $this->forge->addPrimaryKey(['role_id', 'permission_id']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions');
    }

    public function down(): void
    {
        $this->forge->dropTable('role_permissions');
    }
}
