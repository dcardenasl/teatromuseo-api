<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the state required for refresh-token family rotation and immediate
 * account-wide JWT invalidation.
 *
 * Existing refresh rows receive their own family and keep a null reason when
 * the historical code cannot tell whether they were revoked by logout or by
 * rotation. New rows always write an explicit reason.
 */
final class HardenTokenLifecycle extends Migration
{
    public function up(): void
    {
        $this->resetSchemaCache();

        if (! $this->db->fieldExists('auth_token_version', 'users')) {
            $this->forge->addColumn('users', [
                'auth_token_version' => [
                    'type'     => 'BIGINT',
                    'unsigned' => true,
                    'null'     => false,
                    'default'  => 0,
                ],
            ]);
            $this->resetSchemaCache();
        }

        $refreshColumns = [];
        if (! $this->db->fieldExists('family_id', 'refresh_tokens')) {
            $refreshColumns['family_id'] = [
                'type'       => 'CHAR',
                'constraint' => 32,
                'null'       => true,
            ];
        }
        if (! $this->db->fieldExists('parent_id', 'refresh_tokens')) {
            $refreshColumns['parent_id'] = [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ];
        }
        if (! $this->db->fieldExists('revoked_reason', 'refresh_tokens')) {
            $refreshColumns['revoked_reason'] = [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ];
        }

        if ($refreshColumns !== []) {
            $this->forge->addColumn('refresh_tokens', $refreshColumns);
            $this->resetSchemaCache();
        }

        $this->backfillRefreshFamilies();
        $this->makeFamilyRequiredOnProductionDrivers();
        $this->createIndexes();
    }

    public function down(): void
    {
        $this->resetSchemaCache();

        $this->dropIndexIfPresent('refresh_tokens', 'idx_refresh_tokens_user_family_state');
        $this->dropIndexIfPresent('refresh_tokens', 'idx_refresh_tokens_parent');

        foreach (['family_id', 'parent_id', 'revoked_reason'] as $column) {
            if ($this->db->fieldExists($column, 'refresh_tokens')) {
                $this->forge->dropColumn('refresh_tokens', $column);
                $this->resetSchemaCache();
            }
        }

        if ($this->db->fieldExists('auth_token_version', 'users')) {
            $this->forge->dropColumn('users', 'auth_token_version');
            $this->resetSchemaCache();
        }
    }

    private function backfillRefreshFamilies(): void
    {
        if (! $this->db->fieldExists('family_id', 'refresh_tokens')) {
            return;
        }

        $rows = $this->db->table('refresh_tokens')
            ->select('id')
            ->where('family_id', null)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('refresh_tokens')
                ->where('id', (int) $row['id'])
                ->update(['family_id' => bin2hex(random_bytes(16))]);
        }
    }

    private function makeFamilyRequiredOnProductionDrivers(): void
    {
        if (strtolower((string) $this->db->getPlatform()) === 'sqlite3') {
            // SQLite cannot alter a populated column to NOT NULL without a
            // table rebuild. The model and migration backfill enforce the
            // invariant on the supported production driver (MySQL).
            return;
        }

        $this->forge->modifyColumn('refresh_tokens', [
            'family_id' => [
                'type'       => 'CHAR',
                'constraint' => 32,
                'null'       => false,
            ],
        ]);
        $this->resetSchemaCache();
    }

    private function createIndexes(): void
    {
        $this->createIndexIfMissing(
            'refresh_tokens',
            'idx_refresh_tokens_user_family_state',
            ['user_id', 'family_id', 'revoked_at']
        );
        $this->createIndexIfMissing('refresh_tokens', 'idx_refresh_tokens_parent', ['parent_id']);
    }

    /**
     * @param list<string> $columns
     */
    private function createIndexIfMissing(string $table, string $name, array $columns): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $this->db->query(sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $this->quoteIdentifier($name),
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns))
        ));
        $this->resetSchemaCache();
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        if (strtolower((string) $this->db->getPlatform()) === 'sqlite3') {
            $this->db->query('DROP INDEX ' . $this->quoteIdentifier($name));
            $this->resetSchemaCache();
            return;
        }

        $this->forge->dropKey($table, $name);
        $this->resetSchemaCache();
    }

    private function resetSchemaCache(): void
    {
        $this->db->resetDataCache();
    }

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

        foreach ($this->db->getIndexData($table) as $index) {
            if (strcasecmp((string) $index->name, $indexName) === 0) {
                return true;
            }
        }

        return false;
    }

    private function quoteIdentifier(string $identifier): string
    {
        $platform = strtolower((string) $this->db->getPlatform());
        $quote = in_array($platform, ['mysqli', 'mysql'], true) ? '`' : '"';

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }
}
