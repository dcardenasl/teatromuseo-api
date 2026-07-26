<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class ValidationI18nConventionsTest extends CIUnitTestCase
{
    public function testValidationExceptionsDoNotUseHardcodedErrorMessages(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app'));
        $violations = [];

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
            if ($relative === 'app/Commands/MakeCrud.php') {
                continue;
            }

            $source = file_get_contents($path);
            if (! is_string($source) || $source === '') {
                continue;
            }

            if (! preg_match_all('/throw new\s+[A-Za-z0-9_\\\\]*ValidationException\s*\((.*?)\);/s', $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $args = $match[0];
                $argsOffset = $match[1];

                if (! str_contains($args, '[') || ! str_contains($args, '=>')) {
                    continue;
                }

                if (! preg_match_all('/=>\s*([\'"])([^\'"]+)\1/', $args, $arrayValueMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($arrayValueMatches as $valueMatch) {
                    $value = $valueMatch[2][0];
                    $valueOffset = $valueMatch[2][1];
                    $lineNumber = substr_count(substr($source, 0, $argsOffset + $valueOffset), "\n") + 1;
                    $violations[] = "{$relative}:{$lineNumber} hardcoded ValidationException message ({$value})";
                }
            }
        }

        $this->assertSame([], $violations, "Validation i18n convention violations:\n- " . implode("\n- ", $violations));
    }
}
