<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMembershipRolesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'membership_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'role_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);

        $this->forge->addPrimaryKey(['membership_id', 'role_id']);
        $this->forge->addForeignKey('membership_id', 'app_user_memberships', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('membership_roles');
    }

    public function down(): void
    {
        $this->forge->dropTable('membership_roles', true);
    }
}
