<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Storage\Drivers\LocalDriver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * LocalDriver Tests
 */
class LocalDriverTest extends CIUnitTestCase
{
    protected LocalDriver $driver;
    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set environment variable for test upload path
        $this->testPath = WRITEPATH . 'tests/uploads/';
        putenv('FILE_UPLOAD_PATH=' . $this->testPath);

        $this->driver = new LocalDriver();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directory
        if (is_dir($this->testPath)) {
            $this->deleteDirectory($this->testPath);
        }

        putenv('FILE_UPLOAD_PATH');
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testStoreCreatesFile(): void
    {
        $result = $this->driver->store('test.txt', 'Hello World');

        $this->assertTrue($result);
        $this->assertTrue($this->driver->exists('test.txt'));
    }

    public function testStoreWithResourceCreatesFile(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'Stream content');
        rewind($resource);

        $result = $this->driver->store('stream.txt', $resource);

        fclose($resource);

        $this->assertTrue($result);
        $this->assertTrue($this->driver->exists('stream.txt'));
    }

    public function testRetrieveReturnsFileContents(): void
    {
        $this->driver->store('test.txt', 'Hello World');

        $content = $this->driver->retrieve('test.txt');

        $this->assertEquals('Hello World', $content);
    }

    public function testRetrieveReturnsFalseForNonExistentFile(): void
    {
        $content = $this->driver->retrieve('nonexistent.txt');

        $this->assertFalse($content);
    }

    public function testDeleteRemovesFile(): void
    {
        $this->driver->store('test.txt', 'Hello World');
        $this->assertTrue($this->driver->exists('test.txt'));

        $result = $this->driver->delete('test.txt');

        $this->assertTrue($result);
        $this->assertFalse($this->driver->exists('test.txt'));
    }

    public function testDeleteHandlesNonExistentFile(): void
    {
        // Flysystem may return true even for non-existent files (no-op is successful)
        $result = $this->driver->delete('nonexistent.txt');

        // Just verify it doesn't throw an exception
        $this->assertIsBool($result);
    }

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $this->driver->store('test.txt', 'Hello World');

        $exists = $this->driver->exists('test.txt');

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseForNonExistentFile(): void
    {
        $exists = $this->driver->exists('nonexistent.txt');

        $this->assertFalse($exists);
    }

    public function testUrlReturnsPublicUrl(): void
    {
        $url = $this->driver->url('test.txt');

        $this->assertStringContainsString('test.txt', $url);
        $this->assertStringStartsWith('http', $url);
    }

    public function testSizeReturnsFileSizeInBytes(): void
    {
        $this->driver->store('test.txt', 'Hello World');

        $size = $this->driver->size('test.txt');

        $this->assertEquals(11, $size); // "Hello World" is 11 bytes
    }

    public function testSizeReturnsFalseForNonExistentFile(): void
    {
        $size = $this->driver->size('nonexistent.txt');

        $this->assertFalse($size);
    }

    public function testStoreInNestedDirectory(): void
    {
        $result = $this->driver->store('nested/dir/test.txt', 'Nested content');

        $this->assertTrue($result);
        $this->assertTrue($this->driver->exists('nested/dir/test.txt'));
    }
}
