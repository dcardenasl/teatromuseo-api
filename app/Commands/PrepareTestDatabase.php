<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Seeds\RbacBootstrapSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Database\Seeder;
use Config\Database;
use Config\Migrations;

class PrepareTestDatabase extends BaseCommand
{
    protected $group = 'Tests';
    protected $name = 'tests:prepare-db';
    protected $description = 'Drop all tables in the tests database and rerun the App migrations.';
    protected $usage = 'tests:prepare-db';

    public function run(array $params)
    {
        CLI::write('Preparing test database (group "tests").');

        $db = $this->connectToTestsDatabase();
        if ($db === null) {
            return EXIT_ERROR;
        }

        $isSqlite = strtolower($db->DBDriver) === 'sqlite3';
        $this->dropAllTables($db);

        if ($isSqlite) {
            $db->close();
            $db = $this->connectToTestsDatabase();
            if ($db === null) {
                return EXIT_ERROR;
            }
        }
        if (! $isSqlite) {
            $this->resetMigrationHistory($db);
        }
        $this->migrateAppSchema($db);
        $ready = $this->ensureExpectedTablesPresent($db);

        if (! $ready) {
            CLI::error('Post-migration verification failed. Inspect the database and rerun the command.');
            return EXIT_ERROR;
        }

        $this->seedRbacBootstrap($db);

        CLI::write('Test database prepared.', 'green');
        return EXIT_SUCCESS;
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function seedRbacBootstrap(BaseConnection $db): void
    {
        $config = new \Config\Database();
        $seeder = new Seeder($config, $db);
        $seeder->call(RbacBootstrapSeeder::class);
        CLI::write('Seeded IAM bootstrap (applications, roles, permissions).', 'green');
    }

    /**
     * @return BaseConnection<object, object>|null
     */
    private function connectToTestsDatabase(): ?BaseConnection
    {
        try {
            $connection = Database::connect('tests');
            $connection->initialize();
            return $connection;
        } catch (DatabaseException $e) {
            CLI::error('Unable to connect to the tests database: ' . $e->getMessage());
            CLI::write('Ensure the `ci4_website_builder_api_test` schema exists and the credentials in phpunit.xml/.env match.', 'yellow');
            return null;
        }
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function dropAllTables(BaseConnection $db): void
    {
        $driver = strtolower($db->DBDriver);
        if ($driver === 'sqlite3') {
            $path = $db->database;
            if (is_file($path)) {
                unlink($path);
                CLI::write('Deleted existing SQLite test database file.');
            }
            return;
        }

        $existingTables = $db->listTables();
        $tables         = array_filter(
            $existingTables === false ? [] : $existingTables,
            static fn ($table) => $table !== 'migrations'
        );
        if (empty($tables)) {
            CLI::write('No tables found to drop.');
            return;
        }

        $this->disableForeignKeys($db);

        foreach ($tables as $table) {
            $db->query("DROP TABLE IF EXISTS `$table`");
        }

        $this->enableForeignKeys($db);
        CLI::write('Dropped all existing tables.');
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function disableForeignKeys(BaseConnection $db): void
    {
        $driver = strtolower($db->DBDriver);
        if ($driver === 'mysqli' || $driver === 'mysql') {
            $db->query('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function enableForeignKeys(BaseConnection $db): void
    {
        $driver = strtolower($db->DBDriver);
        if ($driver === 'mysqli' || $driver === 'mysql') {
            $db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function migrateAppSchema(BaseConnection $db): void
    {
        $config = new Migrations();
        $config->enabled = true;

        /** @var MigrationRunner $runner */
        $runner = service('migrations', $config, $db, false);
        $runner->setSilent(false);
        $runner->setNamespace('App');
        $runner->latest('tests');
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function resetMigrationHistory(BaseConnection $db): void
    {
        if (! $db->tableExists('migrations')) {
            return;
        }

        $db->table('migrations')
            ->where('group', 'tests')
            ->delete();
    }

    /**
     * @param BaseConnection<object, object> $db
     */
    private function ensureExpectedTablesPresent(BaseConnection $db): bool
    {
        $existingTables = $db->listTables();
        $tables         = $existingTables === false ? [] : $existingTables;
        $required       = ['users', 'migrations'];
        foreach ($required as $table) {
            if (! in_array($table, $tables, true)) {
                CLI::error("Required table `{$table}` is missing after migrations.");
                return false;
            }
        }

        $count = 0;
        if ($db->tableExists('migrations')) {
            $count = $db->table('migrations')->countAllResults(false);
        }

        if ($count === 0) {
            CLI::error('`migrations` table does not contain entries.');
            return false;
        }

        return true;
    }
}
