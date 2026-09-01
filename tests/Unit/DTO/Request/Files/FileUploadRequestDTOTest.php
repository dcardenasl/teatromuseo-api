<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Request\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

/**
 * @internal
 */
final class FileUploadRequestDTOTest extends CIUnitTestCase
{
    public function testThrowsAuthenticationExceptionWhenUserIdMissing(): void
    {
        $this->expectException(AuthenticationException::class);

        new FileUploadRequestDTO(['file' => str_repeat('a', 150)], service('validation'));
    }

    public function testThrowsAuthenticationExceptionWhenUserIdNotNumeric(): void
    {
        $this->expectException(AuthenticationException::class);

        new FileUploadRequestDTO(['user_id' => 'not-numeric', 'file' => str_repeat('a', 150)], service('validation'));
    }

    public function testThrowsBadRequestExceptionWhenNoFilePresent(): void
    {
        $this->expectException(BadRequestException::class);

        new FileUploadRequestDTO(['user_id' => 1], service('validation'));
    }

    public function testAcceptsUploadedFileInstanceDirectly(): void
    {
        // sanitizeValueForLog() calls UploadedFile::getMimeType(), which
        // reads the real file at $path via finfo — needs a file that
        // actually exists on disk, unlike the array-shape conversion tests
        // below (those never construct the UploadedFile through this path
        // before logging happens for a *different* key).
        $realPath = tempnam(sys_get_temp_dir(), 'upload-dto-test-');
        file_put_contents($realPath, 'fake image bytes');

        try {
            $uploaded = new UploadedFile($realPath, 'photo.jpg', 'image/jpeg', 1024, 0);

            $dto = new FileUploadRequestDTO(['user_id' => 1, 'file' => $uploaded], service('validation'));

            $this->assertSame($uploaded, $dto->file);
            $this->assertFalse($dto->isBase64());
        } finally {
            @unlink($realPath);
        }
    }

    public function testAcceptsLongBase64StringAsFile(): void
    {
        $base64 = 'data:image/png;base64,' . str_repeat('A', 200);

        $dto = new FileUploadRequestDTO(['user_id' => 1, 'file' => $base64], service('validation'));

        $this->assertSame($base64, $dto->file);
        $this->assertTrue($dto->isBase64());
    }

    public function testShortStringUnderFileKeyIsIgnoredAndFallsThroughToOtherKeys(): void
    {
        // 'file' is a short plain string (< 100 chars, no data: prefix) so it
        // is skipped; the large payload elsewhere in the array is picked up
        // by the fallback search instead.
        $dto = new FileUploadRequestDTO([
            'user_id' => 1,
            'file' => 'short',
            'payload' => str_repeat('B', 1200),
        ], service('validation'));

        $this->assertSame(str_repeat('B', 1200), $dto->file);
    }

    public function testConvertsFileArrayShapeToUploadedFile(): void
    {
        $dto = new FileUploadRequestDTO([
            'user_id' => 1,
            'file' => [
                'tmp_name' => '/tmp/fake-path',
                'name' => 'doc.pdf',
                'type' => 'application/pdf',
                'size' => 2048,
                'error' => 0,
            ],
        ], service('validation'));

        $this->assertInstanceOf(UploadedFile::class, $dto->file);
        $this->assertSame('doc.pdf', $dto->file->getName());
    }

    public function testFindsNestedUploadedFileArrayInPayload(): void
    {
        $dto = new FileUploadRequestDTO([
            'user_id' => 1,
            'wrapper' => [
                'nested' => [
                    'tmp_name' => '/tmp/fake-path',
                    'name' => 'nested.pdf',
                ],
            ],
        ], service('validation'));

        $this->assertInstanceOf(UploadedFile::class, $dto->file);
        $this->assertSame('nested.pdf', $dto->file->getName());
    }

    public function testMapsOptionalFilenameAndVisibility(): void
    {
        $dto = new FileUploadRequestDTO([
            'user_id' => 1,
            'file' => str_repeat('a', 150),
            'filename' => 'custom.txt',
            'visibility' => 'PUBLIC',
        ], service('validation'));

        $this->assertSame('custom.txt', $dto->filename);
        $this->assertSame('public', $dto->visibility);
    }

    public function testToArrayReturnsAllMappedFields(): void
    {
        $dto = new FileUploadRequestDTO([
            'user_id' => 5,
            'file' => str_repeat('a', 150),
        ], service('validation'));

        $data = $dto->toArray();
        $this->assertSame(5, $data['user_id']);
        $this->assertArrayHasKey('filename', $data);
        $this->assertArrayHasKey('visibility', $data);
    }
}
