<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail to keep service layer independent from runtime/framework globals.
 */
class ServicePurityConventionsTest extends CIUnitTestCase
{
    public function testServicesDoNotResolveRuntimeDependenciesOrEnvDirectly(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $serviceDir = $root . DIRECTORY_SEPARATOR . 'app/Services';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($serviceDir));
        $violations = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
            $source = file_get_contents($path);

            if (!is_string($source) || $source === '') {
                continue;
            }

            if (preg_match('/\\\Config\\\Services::/', $source) === 1) {
                $violations[] = "{$relative}: direct Config\\Services usage is forbidden in Services layer";
            }

            if (preg_match('/^use\s+Config\\\\Services(?:\s+as\s+\w+)?\s*;/m', $source) === 1) {
                $violations[] = "{$relative}: importing Config\\Services is forbidden in Services layer";
            }

            if (preg_match('/\bServices::/', $source) === 1) {
                $violations[] = "{$relative}: static Services facade usage is forbidden in Services layer";
            }

            if (preg_match('/\benv\s*\(/', $source) === 1 || preg_match('/\bgetenv\s*\(/', $source) === 1) {
                $violations[] = "{$relative}: direct env()/getenv() usage is forbidden in Services layer";
            }
        }

        $this->assertSame([], $violations, "Service purity convention violations:\n- " . implode("\n- ", $violations));
    }
}
