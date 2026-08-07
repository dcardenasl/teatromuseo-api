<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Read-only diagnostic command for auditing files on disk vs database records in `files`.
 * Does not perform any destructive delete operations.
 */
class FilesAuditCommand extends BaseCommand
{
    protected $group = 'Files';
    protected $name = 'files:audit';
    protected $description = 'Audits physical disk files against database records in files table';
    protected $usage = 'php spark files:audit';

    public function run(array $params): void
    {
        CLI::write('Filesystem & Database Storage Audit (Read-Only)', 'cyan');
        CLI::write(str_repeat('=', 60), 'cyan');

        $apiConfig = config('Api');
        $uploadPathRel = $apiConfig->fileUploadPath ?? 'writable/uploads';

        $projectRoot = dirname(FCPATH);
        $fullUploadPath = rtrim($projectRoot, '/') . '/' . ltrim(rtrim($uploadPathRel, '/'), '/');

        CLI::write("Upload Path: {$fullUploadPath}", 'info');

        if (!is_dir($fullUploadPath)) {
            CLI::write('Upload directory does not exist!', 'red');
            return;
        }

        // 1. Load all records from database `files`
        $db = Database::connect();
        $builder = $db->table('files');
        $queryResult = $builder->select('id, path, original_name')->get();
        /** @var list<array{id: int, path: string, original_name: string}> $dbFiles */
        $dbFiles = $queryResult !== false ? $queryResult->getResultArray() : [];

        $dbPaths = [];
        foreach ($dbFiles as $row) {
            $dbPaths[$row['path']] = $row['id'];
        }

        CLI::write(sprintf('Total database records in `files`: %d', count($dbFiles)), 'yellow');

        // 2. Scan physical disk files
        $diskFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullUploadPath, \FilesystemIterator::SKIP_DOTS)
        );

        $totalDiskSize = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $fullPath = $file->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', str_replace($fullUploadPath, '', $fullPath)), '/');
            $size = $file->getSize();
            $totalDiskSize += $size;
            $diskFiles[$relativePath] = $size;
        }

        CLI::write(sprintf('Total physical files on disk: %d (%.2f MB)', count($diskFiles), $totalDiskSize / (1024 * 1024)), 'yellow');

        CLI::write(str_repeat('-', 60), 'cyan');

        // 3. Find files on disk without DB record
        $untrackedOnDisk = [];
        foreach ($diskFiles as $path => $size) {
            if (!isset($dbPaths[$path])) {
                $untrackedOnDisk[$path] = $size;
            }
        }

        // 4. Find DB records without physical disk file
        $missingOnDisk = [];
        foreach ($dbFiles as $row) {
            $path = $row['path'];
            if (!isset($diskFiles[$path])) {
                $missingOnDisk[] = $row;
            }
        }

        // Report Findings
        CLI::write('\n1. Physical files on disk with NO database record:', 'yellow');
        if (empty($untrackedOnDisk)) {
            CLI::write('   ✓ None (all disk files are tracked in database)', 'green');
        } else {
            CLI::write(sprintf('   ✗ Found %d untracked files on disk:', count($untrackedOnDisk)), 'red');
            $count = 0;
            foreach ($untrackedOnDisk as $path => $size) {
                if ($count++ < 10) {
                    CLI::write(sprintf('     - %s (%.2f KB)', $path, $size / 1024), 'white');
                }
            }
            if (count($untrackedOnDisk) > 10) {
                CLI::write(sprintf('     ... and %d more', count($untrackedOnDisk) - 10), 'gray');
            }
        }

        CLI::write('\n2. Database records with MISSING physical disk files:', 'yellow');
        if (empty($missingOnDisk)) {
            CLI::write('   ✓ None (all database records exist on disk)', 'green');
        } else {
            CLI::write(sprintf('   ✗ Found %d missing files:', count($missingOnDisk)), 'red');
            $count = 0;
            foreach ($missingOnDisk as $row) {
                if ($count++ < 10) {
                    CLI::write(sprintf('     - ID #%d: %s (%s)', $row['id'], $row['path'], $row['original_name']), 'white');
                }
            }
            if (count($missingOnDisk) > 10) {
                CLI::write(sprintf('     ... and %d more', count($missingOnDisk) - 10), 'gray');
            }
        }

        CLI::write('\n' . str_repeat('=', 60), 'cyan');
        CLI::write('Audit Summary Complete.', 'green');
    }
}
