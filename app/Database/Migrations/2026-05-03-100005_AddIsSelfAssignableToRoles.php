<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsSelfAssignableToRoles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('roles', [
            'is_self_assignable' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'is_system',
            ],
        ]);

        // Mark the seeded 'user' role as self-assignable (used by public registration).
        $this->db->table('roles')->where('code', 'user')->update(['is_self_assignable' => 1]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('roles', 'is_self_assignable');
    }
}
