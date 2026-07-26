<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds indexes on filterable / searchable / sortable columns that the
 * original CreateXxxTable migrations missed.
 *
 * Identified during the May 2026 technical-debt audit (finding A5).
 * Each addition is idempotent — re-running the migration on a database
 * that already has the index is a no-op.
 *
 * Tables and indexes added:
 *   - users:       (status), (email_verified_at), (created_at),
 *                  composite (oauth_provider, oauth_id) for OAuth lookups
 *   - files:       (uploaded_at), (mime_type), (storage_driver)
 *   - api_keys:    (is_active), (created_at)
 *   - password_resets: (expires_at) for cleanup jobs
 */
class AddMissingIndexesAuditMay2026 extends Migration
{
    /**
     * @var list<array{table: string, name: string, columns: list<string>, unique?: bool}>
     */
    private array $indexes = [
        ['table' => 'users',           'name' => 'idx_users_status',                'columns' => ['status']],
        ['table' => 'users',           'name' => 'idx_users_email_verified_at',     'columns' => ['email_verified_at']],
        ['table' => 'users',           'name' => 'idx_users_created_at',            'columns' => ['created_at']],
        ['table' => 'users',           'name' => 'idx_users_oauth_lookup',          'columns' => ['oauth_provider', 'oauth_id']],
        ['table' => 'files',           'name' => 'idx_files_uploaded_at',           'columns' => ['uploaded_at']],
        ['table' => 'files',           'name' => 'idx_files_mime_type',             'columns' => ['mime_type']],
        ['table' => 'files',           'name' => 'idx_files_storage_driver',        'columns' => ['storage_driver']],
        ['table' => 'api_keys',        'name' => 'idx_api_keys_is_active',          'columns' => ['is_active']],
        ['table' => 'api_keys',        'name' => 'idx_api_keys_created_at',         'columns' => ['created_at']],
        ['table' => 'password_resets', 'name' => 'idx_password_resets_expires_at',  'columns' => ['expires_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $idx) {
            if (! $this->db->tableExists($idx['table'])) {
                continue;
            }
            if (! $this->columnsExist($idx['table'], $idx['columns'])) {
                continue;
            }
            if ($this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }

            $cols = implode(', ', array_map([$this, 'quoteIdentifier'], $idx['columns']));
            $this->db->query(sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->quoteIdentifier($idx['name']),
                $this->quoteIdentifier($idx['table']),
                $cols
            ));
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes) as $idx) {
            if (! $this->db->tableExists($idx['table'])) {
                continue;
            }
            if (! $this->indexExists($idx['table'], $idx['name'])) {
                continue;
            }

            try {
                $this->forge->dropKey($idx['table'], $idx['name']);
            } catch (\Throwable) {
                // Some drivers want raw SQL — fall through.
                $this->db->query(sprintf(
                    'DROP INDEX %s ON %s',
                    $this->quoteIdentifier($idx['name']),
                    $this->quoteIdentifier($idx['table'])
                ));
            }
        }
    }

    /**
     * Cross-driver index existence check.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $platform = strtolower((string) $this->db->getPlatform());

        if ($platform === 'sqlite3') {
            $row = $this->db->query(
                'SELECT name FROM sqlite_master WHERE type = ? AND name = ?',
                ['index', $indexName]
            )->getRowArray();

            return $row !== null;
        }

        // MySQL / MariaDB / Postgres all expose information_schema.statistics or
        // the equivalent. CI4 abstracts this through getIndexData(), which is
        // the safest cross-driver path.
        $existing = $this->db->getIndexData($table);
        foreach ($existing as $info) {
            if (strcasecmp((string) $info->name, $indexName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $columns
     */
    private function columnsExist(string $table, array $columns): bool
    {
        $fields = $this->db->getFieldNames($table);
        foreach ($columns as $col) {
            if (! in_array($col, $fields, true)) {
                return false;
            }
        }

        return true;
    }

    private function quoteIdentifier(string $name): string
    {
        $platform = strtolower((string) $this->db->getPlatform());
        $char     = $platform === 'mysqli' || $platform === 'mysql' ? '`' : '"';

        return $char . str_replace($char, $char . $char, $name) . $char;
    }
}
