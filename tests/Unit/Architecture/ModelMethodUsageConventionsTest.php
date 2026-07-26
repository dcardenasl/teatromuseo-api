<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class ModelMethodUsageConventionsTest extends CIUnitTestCase
{
    public function testCustomPublicModelMethodsAreReferencedOutsideTheirOwnModel(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $modelsDir = $root . DIRECTORY_SEPARATOR . 'app/Models';

        $modelFiles = glob($modelsDir . '/*.php') ?: [];
        sort($modelFiles);

        $allSourceFiles = [];
        foreach ([$root . '/app', $root . '/tests'] as $scanDir) {
            if (!is_dir($scanDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scanDir));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                if (!str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $allSourceFiles[] = $file->getPathname();
            }
        }

        $violations = [];

        foreach ($modelFiles as $path) {
            $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

            if ($relative === 'app/Models/BaseAuditableModel.php') {
                continue;
            }

            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            if (preg_match_all('/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $source, $matches) !== 1) {
                continue;
            }

            $methods = array_unique($matches[1]);
            foreach ($methods as $methodName) {
                if ($methodName === '__construct') {
                    continue;
                }

                $isReferenced = false;
                $arrowPattern = '->' . $methodName . '(';
                $staticPattern = '::' . $methodName . '(';

                foreach ($allSourceFiles as $candidate) {
                    if (!is_string($candidate) || $candidate === '' || $candidate === $path) {
                        continue;
                    }

                    $candidateSource = file_get_contents($candidate);
                    if (!is_string($candidateSource) || $candidateSource === '') {
                        continue;
                    }

                    if (str_contains($candidateSource, $arrowPattern) || str_contains($candidateSource, $staticPattern)) {
                        $isReferenced = true;
                        break;
                    }
                }

                if (!$isReferenced) {
                    $violations[] = "{$relative}: public method {$methodName}() appears unused outside model";
                }
            }
        }

        $this->assertSame([], $violations, "Potentially dead model helpers detected:\n- " . implode("\n- ", $violations));
    }
}
