<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Files;

use App\Libraries\Files\MultipartProcessor;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * @internal
 */
final class MultipartProcessorTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        putenv('FILE_MAX_SIZE');
        unset($_ENV['FILE_MAX_SIZE'], $_SERVER['FILE_MAX_SIZE']);
        parent::tearDown();
    }

    public function testUses10MbFallbackWhenEnvIsMissing(): void
    {
        putenv('FILE_MAX_SIZE');
        unset($_ENV['FILE_MAX_SIZE'], $_SERVER['FILE_MAX_SIZE']);

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(10485761); // 10MB + 1 byte
        $file->method('getExtension')->willReturn('jpg');

        $processor = new MultipartProcessor();

        try {
            $processor->process($file);
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame(lang('Files.file_too_large'), $e->getMessage());
            $this->assertSame(['file' => lang('Files.file_too_large')], $e->getErrors());
        }
    }
}
