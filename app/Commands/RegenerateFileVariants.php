<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RegenerateFileVariants extends BaseCommand
{
    protected $group = 'Files';
    protected $name = 'files:regenerate-all-variants';
    protected $description = 'Regenerate image variants for all files';
    protected $usage = 'php spark files:regenerate-all-variants';

    public function run(array $params): void
    {
        $db = \Config\Database::connect();
        $imageVariantProcessor = new \App\Libraries\Files\ImageVariantProcessor();
        $storage = new \App\Libraries\Storage\StorageManager();

        $result = $db->table('files')->get();
        $files  = $result === false ? [] : $result->getResult('array');

        if (empty($files)) {
            CLI::write('No files found.', 'yellow');
            return;
        }

        $total = count($files);
        $processed = 0;
        $skipped = 0;

        CLI::write("Processing {$total} files...\n", 'cyan');

        foreach ($files as $file) {
            // Only process images
            if (!str_starts_with($file['mime_type'] ?? '', 'image/')) {
                CLI::write("⊘ Skipped (not an image): {$file['original_name']}", 'yellow');
                $skipped++;
                continue;
            }

            try {
                CLI::write("Regenerating: {$file['original_name']}...", 'info');

                $result = $imageVariantProcessor->generate(
                    $file['path'],
                    pathinfo($file['path'], PATHINFO_EXTENSION),
                    $storage
                );

                CLI::write("  Generated variants: " . json_encode(array_keys($result['variants'] ?? [])), 'gray');

                // Update the file record with the new variants
                $variantsJson = !empty($result['variants']) ? json_encode($result['variants']) : null;
                $updateResult = $db->table('files')
                    ->where('id', $file['id'])
                    ->update(['variants' => $variantsJson]);

                if (!$updateResult) {
                    CLI::write("  ⚠ Warning: Update returned false", 'yellow');
                } else {
                    CLI::write("  ✓ Variants saved", 'green');
                }

                CLI::write("✓ Done", 'green');
                $processed++;
            } catch (\Throwable $e) {
                CLI::write("✗ Error: {$e->getMessage()}", 'red');
                $skipped++;
            }
        }

        CLI::write("\n" . str_repeat('=', 50), 'cyan');
        CLI::write("Regeneration complete!", 'green');
        CLI::write("Processed: {$processed}/{$total}", 'cyan');
        if ($skipped > 0) {
            CLI::write("Skipped: {$skipped}", 'yellow');
        }
    }
}
