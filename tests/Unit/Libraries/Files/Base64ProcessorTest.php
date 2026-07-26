<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Files;

use App\Libraries\Files\Base64Processor;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * @internal
 */
final class Base64ProcessorTest extends CIUnitTestCase
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

        $payload = str_repeat('A', 10485761); // 10MB + 1 byte decoded payload
        $base64 = base64_encode($payload);

        $processor = new Base64Processor();

        try {
            $processor->process('data:image/png;base64,' . $base64);
            $this->fail('ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame(lang('Files.file_too_large'), $e->getMessage());
            $this->assertSame(['file' => lang('Files.file_too_large')], $e->getErrors());
        }
    }
}
