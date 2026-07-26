<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class BaseRequestDtoConventionsTest extends CIUnitTestCase
{
    public function testAllRequestDtosDeclarePublicRulesMethod(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $dtoDir = $root . '/app/DTO/Request';
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dtoDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), 'DTO.php')) {
                continue;
            }

            if ($file->getFilename() === 'BaseRequestDTO.php') {
                continue;
            }

            $path = $file->getPathname();
            $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
            $fqcn = 'App\\' . str_replace(['/', '.php'], ['\\', ''], str_replace($root . '/app/', '', $path));

            if (!class_exists($fqcn)) {
                $violations[] = "{$relative}: class could not be loaded";
                continue;
            }

            $reflection = new \ReflectionClass($fqcn);
            if (!$reflection->isSubclassOf(\dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO::class)) {
                $violations[] = "{$relative}: does not extend BaseRequestDTO";
                continue;
            }

            if (!$reflection->hasMethod('rules')) {
                $violations[] = "{$relative}: missing rules()";
                continue;
            }

            $rulesMethod = $reflection->getMethod('rules');
            if (!$rulesMethod->isPublic()) {
                $violations[] = "{$relative}: rules() must be public";
            }
        }

        $this->assertSame([], $violations, "Request DTO visibility violations:\n- " . implode("\n- ", $violations));
    }
}
