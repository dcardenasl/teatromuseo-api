<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestImageProcessing extends BaseCommand
{
    protected $group = 'Files';
    protected $name = 'files:test-image-processing';
    protected $description = 'Test if image processing works';
    protected $usage = 'php spark files:test-image-processing';

    public function run(array $params): void
    {
        CLI::write('Image Processing Test', 'cyan');
        CLI::write(str_repeat('=', 50), 'cyan');

        // Test 1: Check GD extension
        CLI::write('\n1. Checking GD Extension:', 'yellow');
        if (extension_loaded('gd')) {
            CLI::write('   ✓ GD extension loaded', 'green');
        } else {
            CLI::write('   ✗ GD extension NOT loaded', 'red');
            return;
        }

        // Test 2: Check image functions
        CLI::write('\n2. Checking Image Functions:', 'yellow');
        $functions = ['imagecreatefromstring', 'imagecopyresampled'];
        foreach ($functions as $func) {
            if (function_exists($func)) {
                CLI::write("   ✓ {$func}() available", 'green');
            } else {
                CLI::write("   ✗ {$func}() NOT available", 'red');
            }
        }

        // Test 3: Get an actual file and try to process it
        CLI::write('\n3. Testing with Real File:', 'yellow');
        $db     = \Config\Database::connect();
        $result = $db->table('files')
            ->where('mime_type', 'image/png')
            ->orWhere('mime_type', 'image/jpeg')
            ->get();
        $file   = $result === false ? null : $result->getRow();

        if (!$file) {
            CLI::write('   No image files found in DB', 'yellow');
            return;
        }

        CLI::write("   Found file: {$file->original_name}", 'info');

        $filePath = FCPATH . '/../' . $file->path;
        CLI::write("   File path: {$filePath}", 'info');

        if (!file_exists($filePath)) {
            CLI::write("   ✗ File does not exist", 'red');
            return;
        }

        CLI::write("   ✓ File exists", 'green');

        // Test 4: Try to process the image
        CLI::write('\n4. Testing Image Processing:', 'yellow');

        try {
            $imageLib = \Config\Services::image('gd', null, false);
            CLI::write("   ✓ Image library initialized", 'green');

            $imageLib->withFile($filePath);
            CLI::write("   ✓ Image loaded", 'green');

            // Try to get image info
            $origSize = getimagesize($filePath);
            if ($origSize) {
                CLI::write("   ✓ Original dimensions: {$origSize[0]}x{$origSize[1]}", 'green');
            }

            // Try to resize
            $tmpOutput = sys_get_temp_dir() . '/test_resize.png';
            $imageLib->resize(400, 0, true, 'width');
            $imageLib->save($tmpOutput);

            if (file_exists($tmpOutput)) {
                CLI::write("   ✓ Resize successful", 'green');
                $newSize = getimagesize($tmpOutput);
                if ($newSize) {
                    CLI::write("   ✓ New dimensions: {$newSize[0]}x{$newSize[1]}", 'green');
                }
                unlink($tmpOutput);
            } else {
                CLI::write("   ✗ Resize failed - no output file", 'red');
            }
        } catch (\Throwable $e) {
            CLI::write("   ✗ Error: {$e->getMessage()}", 'red');
        }

        CLI::write('\n' . str_repeat('=', 50), 'cyan');
        CLI::write('Test complete', 'green');
    }
}
