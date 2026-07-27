<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyAssetResolver;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacyAssetResolverTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-assets-' . bin2hex(random_bytes(8));
        mkdir($this->rootPath . '/images', 0755, true);
        file_put_contents($this->rootPath . '/images/example.txt', 'legacy asset fixture');
    }

    protected function tearDown(): void
    {
        @unlink($this->rootPath . '/images/example.txt');
        @rmdir($this->rootPath . '/images');
        @rmdir($this->rootPath);
    }

    public function testResolvedAssetIncludesStableHashAndMetadata(): void
    {
        $result = (new LegacyAssetResolver($this->rootPath))->resolve('/images/example.txt');

        $this->assertSame('resolved', $result['status']);
        $this->assertSame('images/example.txt', $result['relative_path']);
        $this->assertSame(hash('sha256', 'legacy asset fixture'), $result['sha256']);
        $this->assertSame(strlen('legacy asset fixture'), $result['size']);
        $this->assertSame('text/plain', $result['mime_type']);
    }

    public function testMissingAndUnsafeAssetsAreReportedWithoutThrowing(): void
    {
        $resolver = new LegacyAssetResolver($this->rootPath);

        $this->assertSame('missing', $resolver->resolve('/images/not-found.jpg')['status']);
        $this->assertSame('invalid', $resolver->resolve('/../outside.txt')['status']);
    }
}
