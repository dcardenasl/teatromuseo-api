<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Shared base for all integration tests.
 *
 * CI4's DatabaseTestTrait resets its private static $doneMigration via
 * #[AfterClass] after every test class, so migrateOnce=true still triggers
 * a full regress+migrate cycle before each class regardless of inheritance.
 * With 20+ integration test classes that DDL pressure is the root cause of
 * "MySQL server has gone away" errors in CI.
 *
 * Fix: $migrate=false tells CI4 to skip regress/migrate entirely (both
 * methods return early). Inter-class isolation is handled by purging all
 * non-migration tables once at the start of each test class, in setUp()
 * (instance context) rather than setUpBeforeClass() (static context) so
 * that $this->db is properly initialized by loadDependencies() before use.
 * Seeds still run per test method via setUpSeed() (seedOnce defaults to
 * false).
 */
abstract class IntegrationTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected static string $lastPurgedClass = '';

    protected $migrate     = false;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';
    protected $basePath    = APPPATH . 'Database';

    protected function setUp(): void
    {
        // Truncate once per test class: when the calling class changes we know
        // a new class is starting. self:: is used deliberately so all subclasses
        // share the same IntegrationTestCase::$lastPurgedClass storage.
        if (self::$lastPurgedClass !== static::class) {
            self::$lastPurgedClass = static::class;
            $this->loadDependencies();     // ensures $this->db is live
            $this->purgeNonMigrationTables();
        }

        parent::setUp();  // runs setUpSeed() which re-seeds if $seed is set
    }

    private function purgeNonMigrationTables(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = OFF');
        } else {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        }
        foreach ($this->db->listTables() as $table) {
            if ($table !== 'migrations') {
                $this->db->table($table)->truncate();
            }
        }
        if ($this->db->DBDriver === 'SQLite3') {
            try {
                $this->db->query('DELETE FROM sqlite_sequence');
            } catch (\Throwable) {
                // Ignore if sequence table doesn't exist
            }
            $this->db->query('PRAGMA foreign_keys = ON');
        } else {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
