<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail against controllers that extend ApiController but bypass
 * handleRequest() to build responses manually with $this->response->setJSON().
 *
 * LAYER-02 (2026-08-06): UserController::assignableRoles() was fixed to
 * return ApiResponse::success() through handleRequest() instead of
 * constructing ResponseInterface directly. This test prevents regression.
 */
final class ControllerResponseConventionsTest extends CIUnitTestCase
{
    public function testApiControllersDoNotBuildResponsesManually(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $controllerDir = $root . DIRECTORY_SEPARATOR . 'app/Controllers';

        $violations = [];
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

            // Only check controllers that extend ApiController
            if (preg_match('/extends\s+ApiController/', $source) !== 1) {
                continue;
            }

            // Strip comments and string literals
            $code = '';
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                    $code .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }

            // Detect manual setJSON calls
            if (preg_match('/\$this->response->setJSON\s*\(/', $code) === 1) {
                $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));
                $violations[] = $relative;
            }
        }

        sort($violations);
        $this->assertSame(
            [],
            $violations,
            "Controllers extending ApiController must not build responses manually with \$this->response->setJSON().\n" .
            "Return arrays from handleRequest() closures instead, or add a narrowly-scoped exception.\n"
        );
    }
}
