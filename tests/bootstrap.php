<?php

declare(strict_types=1);

if (! defined('EXIT_FAILURE')) {
    define('EXIT_FAILURE', 1);
}

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

// Skip the DB preflight when phpunit is invoked exclusively for the Unit
// suite — Unit tests are DB-free by project convention, and CI's
// `ci4-compatibility` job (Unit-only) runs without a MySQL service.
$requestedSuites = [];
foreach ($_SERVER['argv'] ?? [] as $i => $arg) {
    if ($arg === '--testsuite' && isset($_SERVER['argv'][$i + 1])) {
        $requestedSuites = array_merge($requestedSuites, explode(',', $_SERVER['argv'][$i + 1]));
    } elseif (is_string($arg) && str_starts_with($arg, '--testsuite=')) {
        $requestedSuites = array_merge($requestedSuites, explode(',', substr($arg, 12)));
    }
}

if ($requestedSuites !== [] && array_diff($requestedSuites, ['Unit']) === []) {
    return;
}

try {
    $db = Database::connect('tests');
    $db->initialize();
} catch (DatabaseException $e) {
    CLI::error('Cannot connect to tests database: ' . $e->getMessage());
    CLI::write('Run `php spark tests:prepare-db` before executing phpunit.', 'yellow');
    exit(EXIT_FAILURE);
}

$tables = $db->listTables(false);
if (empty($tables)) {
    CLI::error('Tests database is empty. Run `php spark tests:prepare-db`.');
    exit(EXIT_FAILURE);
}

if (! $db->tableExists('migrations')) {
    CLI::error('`migrations` table missing in tests database. Run `php spark tests:prepare-db`.');
    exit(EXIT_FAILURE);
}

$runner = $db->table('migrations')->countAllResults(false);
if ($runner === 0) {
    CLI::error('`migrations` table has no entries. Run `php spark tests:prepare-db`.');
    exit(EXIT_FAILURE);
}
