<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Files;

use App\Libraries\Files\ImageVariantProcessor;
use App\Libraries\Storage\StorageManager;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ImageVariantProcessorTest extends CIUnitTestCase
{
    public function testDoesNotUpscaleLargeVariantBeyondOriginalDimensions(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for image variant tests.');
        }

        $tempDir = sys_get_temp_dir() . '/ci4_image_variant_' . bin2hex(random_bytes(4));
        mkdir($tempDir);

        try {
            $originalPath = $tempDir . '/sample.png';
            $image = imagecreatetruecolor(640, 480);
            $background = imagecolorallocate($image, 200, 120, 40);
            imagefill($image, 0, 0, $background);
            imagepng($image, $originalPath);
            imagedestroy($image);

            $captured = [];
            $storage = $this->createMock(StorageManager::class);
            $storage->method('get')->willReturnCallback(static fn (string $path): string|false => file_get_contents($path));
            $storage->method('put')->willReturnCallback(function (string $path, $contents) use (&$captured): bool {
                $captured[$path] = $contents;
                return true;
            });
            $storage->method('url')->willReturnCallback(static fn (string $path): string => 'https://cdn.example.test/' . $path);

            $processor = new ImageVariantProcessor();
            $result = $processor->generate($originalPath, 'png', $storage);

            $this->assertArrayHasKey('lg', $result['variants']);
            $this->assertSame('webp', pathinfo($result['variants']['lg']['path'], PATHINFO_EXTENSION));
            $this->assertSame('image/webp', $result['variants']['lg']['mime_type']);
            $this->assertLessThanOrEqual(640, $result['variants']['lg']['width']);
            $this->assertLessThanOrEqual(480, $result['variants']['lg']['height']);
            $this->assertArrayHasKey($result['variants']['lg']['path'], $captured);
        } finally {
            foreach (glob($tempDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }

    public function testGeneratesWebpVariantsForJpegInput(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for image variant tests.');
        }

        $tempDir = sys_get_temp_dir() . '/ci4_image_variant_' . bin2hex(random_bytes(4));
        mkdir($tempDir);

        try {
            $originalPath = $tempDir . '/sample.jpg';
            $image = imagecreatetruecolor(1600, 900);
            $color = imagecolorallocate($image, 10, 20, 30);
            imagefill($image, 0, 0, $color);
            imagejpeg($image, $originalPath, 90);
            imagedestroy($image);

            $storage = $this->createMock(StorageManager::class);
            $storage->method('get')->willReturnCallback(static fn (string $path): string|false => file_get_contents($path));
            $storage->method('put')->willReturn(true);
            $storage->method('url')->willReturnCallback(static fn (string $path): string => 'https://cdn.example.test/' . $path);

            $processor = new ImageVariantProcessor();
            $result = $processor->generate($originalPath, 'jpg', $storage);

            $this->assertNotEmpty($result['variants']);
            foreach ($result['variants'] as $variant) {
                $this->assertSame('webp', pathinfo($variant['path'], PATHINFO_EXTENSION));
                $this->assertSame('image/webp', $variant['mime_type']);
            }
        } finally {
            foreach (glob($tempDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }

    public function testSkipsVariantGenerationForGifInput(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for image variant tests.');
        }

        $tempDir = sys_get_temp_dir() . '/ci4_image_variant_' . bin2hex(random_bytes(4));
        mkdir($tempDir);

        try {
            $originalPath = $tempDir . '/sample.gif';
            $image = imagecreatetruecolor(320, 240);
            $color = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $color);
            imagegif($image, $originalPath);
            imagedestroy($image);

            $storage = $this->createMock(StorageManager::class);
            $storage->expects($this->once())->method('get')->willReturnCallback(static fn (string $path): string|false => file_get_contents($path));
            $storage->expects($this->never())->method('put');
            $storage->expects($this->never())->method('url');

            $processor = new ImageVariantProcessor();
            $result = $processor->generate($originalPath, 'gif', $storage);

            $this->assertSame([], $result['variants']);
            $this->assertSame(['width' => 320, 'height' => 240], $result['dimensions']);
        } finally {
            foreach (glob($tempDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }
}
