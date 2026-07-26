<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagnoseStorage extends BaseCommand
{
    protected $group = 'Files';
    protected $name = 'files:diagnose-storage';
    protected $description = 'Diagnose file storage issues';
    protected $usage = 'php spark files:diagnose-storage';

    public function run(array $params): void
    {
        CLI::write('File Storage Diagnostic', 'cyan');
        CLI::write(str_repeat('=', 50), 'cyan');

        $apiConfig = config('Api');
        $uploadPath = $apiConfig->fileUploadPath;

        // 1. Check configuration
        CLI::write('\n1. Configuration Check:', 'yellow');
        CLI::write("   Upload Path: {$uploadPath}", 'info');
        CLI::write("   Storage Driver: {$apiConfig->fileStorageDriver}", 'info');

        // 2. Check directory exists and is writable
        CLI::write('\n2. Directory Check:', 'yellow');
        // Upload path is relative to project root, not public
        $projectRoot = dirname(FCPATH);
        $fullPath = rtrim($projectRoot, '/') . '/' . ltrim(rtrim($uploadPath, '/'), '/');
        CLI::write("   Full Path: {$fullPath}", 'info');

        if (!is_dir($fullPath)) {
            CLI::write("   ✗ Directory does not exist", 'red');
            CLI::write("   Creating directory...", 'yellow');
            if (@mkdir($fullPath, 0775, true)) {
                CLI::write("   ✓ Directory created", 'green');
            } else {
                CLI::write("   ✗ Failed to create directory", 'red');
                return;
            }
        } else {
            CLI::write("   ✓ Directory exists", 'green');
        }

        if (!is_writable($fullPath)) {
            CLI::write("   ✗ Directory is not writable", 'red');
            CLI::write("   Chmod 775...", 'yellow');
            if (@chmod($fullPath, 0775)) {
                CLI::write("   ✓ Permissions fixed", 'green');
            } else {
                CLI::write("   ✗ Failed to change permissions", 'red');
            }
        } else {
            CLI::write("   ✓ Directory is writable", 'green');
        }

        // 3. Test file write
        CLI::write('\n3. File Write Test:', 'yellow');
        $storage = new \App\Libraries\Storage\StorageManager();
        $testPath = date('Y/m/d') . '/test-' . uniqid() . '.txt';
        $testContent = 'Test file created at ' . date('Y-m-d H:i:s');

        if ($storage->put($testPath, $testContent)) {
            CLI::write("   ✓ File write successful: {$testPath}", 'green');

            // Check if file actually exists
            $fullTestPath = $fullPath . '/' . $testPath;
            if (file_exists($fullTestPath)) {
                CLI::write("   ✓ File exists on disk", 'green');
                $content = file_get_contents($fullTestPath);
                if ($content === $testContent) {
                    CLI::write("   ✓ File content is correct", 'green');
                } else {
                    CLI::write("   ✗ File content mismatch", 'red');
                }

                // Test URL generation
                $url = $storage->url($testPath);
                CLI::write("   Generated URL: {$url}", 'info');

                // Test URL access
                CLI::write("   Testing URL access...", 'yellow');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    CLI::write("   ✓ URL accessible (HTTP {$httpCode})", 'green');
                } else {
                    CLI::write("   ✗ URL not accessible (HTTP {$httpCode})", 'red');
                }

                // Cleanup
                $storage->delete($testPath);
                CLI::write("   ✓ Test file cleaned up", 'green');
            } else {
                CLI::write("   ✗ File does not exist on disk", 'red');
                CLI::write("   Path expected: {$fullTestPath}", 'info');
            }
        } else {
            CLI::write("   ✗ File write failed", 'red');
        }

        CLI::write('\n' . str_repeat('=', 50), 'cyan');
        CLI::write('Diagnostic complete', 'green');
    }
}
