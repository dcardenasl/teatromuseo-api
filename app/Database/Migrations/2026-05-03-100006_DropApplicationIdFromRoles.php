<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Roles become global (cross-app). Their permissions still belong to a specific
 * application via the permissions table, so app scoping is preserved.
 */
class DropApplicationIdFromRoles extends Migration
{
    public function up(): void
    {
        $duplicates = $this->db->table('roles')
            ->select('code, COUNT(*) AS c')
            ->groupBy('code')
            ->having('c >', 1)
            ->get()
            ?->getResultArray() ?? [];

        if ($duplicates !== []) {
            throw new \RuntimeException(sprintf(
                'Cannot drop roles.application_id: duplicate role codes detected (%s). Resolve manually first.',
                implode(', ', array_map(static fn ($r) => (string) $r['code'], $duplicates))
            ));
        }

        $isSqlite = strtolower((string) $this->db->getPlatform()) === 'sqlite3';

        if ($isSqlite) {
            // SQLite cannot DROP COLUMN if the column is part of a FK definition,
            // and it has no DROP CONSTRAINT. Rebuild the table cleanly.
            $this->db->query('PRAGMA foreign_keys = OFF');
            $this->db->query(
                'CREATE TABLE roles_new ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
                . 'code VARCHAR(100) NOT NULL, '
                . 'name VARCHAR(100) NOT NULL, '
                . 'description TEXT NULL, '
                . 'is_system TINYINT NOT NULL DEFAULT 0, '
                . 'is_self_assignable TINYINT NOT NULL DEFAULT 0, '
                . 'created_at DATETIME NOT NULL, '
                . 'updated_at DATETIME NULL'
                . ')'
            );
            $this->db->query(
                'INSERT INTO roles_new (id, code, name, description, is_system, is_self_assignable, created_at, updated_at) '
                . 'SELECT id, code, name, description, is_system, is_self_assignable, created_at, updated_at FROM roles'
            );
            $this->db->query('DROP TABLE roles');
            $this->db->query('ALTER TABLE roles_new RENAME TO roles');
            $this->db->query('CREATE UNIQUE INDEX uniq_roles_code ON roles (code)');
            $this->db->query('PRAGMA foreign_keys = ON');
            return;
        }

        try {
            $this->forge->dropForeignKey('roles', 'roles_application_id_foreign');
        } catch (\Throwable) {
            // FK name may differ across drivers / older migrations; ignore if it doesn't exist.
        }

        $this->forge->dropColumn('roles', 'application_id');

        $this->forge->addKey('code', false, true, 'uniq_roles_code');
        $this->forge->processIndexes('roles');
    }


    public function down(): void
    {
        $this->forge->dropKey('roles', 'uniq_roles_code');

        $this->forge->addColumn('roles', [
            'application_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $this->forge->addForeignKey('application_id', 'applications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->processIndexes('roles');

        $this->db->table('roles')->where('application_id', null)->update(['application_id' => 1]);
    }
}
