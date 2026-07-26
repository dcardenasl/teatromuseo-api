<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

class FileModelConventionsTest extends CIUnitTestCase
{
    public function testFileModelAvoidsLegacyOwnershipHelpers(): void
    {
        $path = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Models/FileModel.php';
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringNotContainsString('function getByUser(', $source);
        $this->assertStringNotContainsString('function getByIdAndUser(', $source);
        $this->assertStringNotContainsString('function deleteByIdAndUser(', $source);
    }
}
