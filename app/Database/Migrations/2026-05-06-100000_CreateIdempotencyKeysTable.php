<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Creates the `idempotency_keys` cache table that backs the
 * `IdempotencyFilter` (audit B7.2 / ADR-009).
 *
 * Each row records the response we want to replay for a given
 * (Idempotency-Key, actor, endpoint) tuple. The `request_hash` column
 * is a SHA-256 of the request body so a retry with the same key but
 * different payload returns 409 Conflict instead of replaying.
 *
 * `expires_at` enables a periodic cleanup job (e.g. cron every hour
 * deleting `expires_at < NOW()`); the index on it makes that scan cheap.
 */
class CreateIdempotencyKeysTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'idempotency_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'actor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'endpoint' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'request_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'response_status' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => false,
            ],
            'response_headers' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'response_body' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('idempotency_key');
        $this->forge->addKey('expires_at');
        $this->forge->addKey(['actor_id', 'endpoint']);
        $this->forge->createTable('idempotency_keys');
    }

    public function down(): void
    {
        $this->forge->dropTable('idempotency_keys', true);
    }
}
