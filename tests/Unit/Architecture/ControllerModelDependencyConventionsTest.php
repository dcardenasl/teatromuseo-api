<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail to keep Controllers delegating to the Service layer instead of
 * touching Models or the database directly.
 *
 * Mirrors ServiceModelDependencyConventionsTest.php's whitelist mechanism,
 * applied one layer up: Controllers must go through Controller -> Service ->
 * Model, never Controller -> Model directly.
 *
 * Added 2026-08-06 (LAYER-04, saneamiento arquitectónico): this app was the
 * one with the real controller->model violation (SelfPermissionsController,
 * InternalFileMetaController — see LAYER-02) and, until now, no guardrail
 * against it recurring. Both were fixed to delegate through
 * Services::selfPermissionService()/Services::fileService() before this test
 * landed, so it starts zero-tolerance — matching teatromuseo-catalog-domain's
 * stricter (no-baseline) version of this test rather than
 * teatromuseo-cms-domain's per-file count-baseline ratchet.
 */
class ControllerModelDependencyConventionsTest extends CIUnitTestCase
{
    public function testControllersDoNotTouchModelsOrDatabaseDirectly(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $controllerDir = $root . DIRECTORY_SEPARATOR . 'app/Controllers';

        $allowed = [];
        sort($allowed);

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            $touchesModelsDirectly = preg_match('/^use\s+App\\\\Models\\\\/m', $source) === 1
                || preg_match('/\bmodel\s*\(/', $source) === 1
                || preg_match('/\\\\?Database\s*::\s*connect\s*\(/', $source) === 1;

            if (!$touchesModelsDirectly) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));
            $found[] = $relative;
        }

        sort($found);
        $this->assertSame(
            $allowed,
            $found,
            "Controllers with direct Model/Database access changed.\n" .
            'Delegate to a Service instead — update this whitelist only for justified exceptions.'
        );
    }
}
