<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds soft-delete support to the `files` table so the admin can trash a file
 * (moving it out of normal listings) and either restore it or force-delete it
 * later. `deleted_at` follows CI4's native soft-delete convention so
 * `FileModel::$useSoftDeletes = true` Just Works. `deleted_by_user_id` records
 * who trashed the file (nullable — no FK because SQLite, used by the test
 * harness, does not support adding FKs to existing tables; integrity is
 * enforced at the service layer).
 */
class AddSoftDeleteToFilesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('files', [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_by_user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        $driver = strtolower((string) $this->db->getPlatform());
        if ($driver !== 'sqlite3') {
            $this->db->query('CREATE INDEX idx_files_deleted_at ON files(deleted_at)');
        }
    }

    public function down(): void
    {
        $driver = strtolower((string) $this->db->getPlatform());
        if ($driver !== 'sqlite3') {
            $this->db->query('DROP INDEX idx_files_deleted_at ON files');
        }
        $this->forge->dropColumn('files', ['deleted_at', 'deleted_by_user_id']);
    }
}
