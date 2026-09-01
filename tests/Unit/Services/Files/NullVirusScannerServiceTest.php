<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Files;

use App\Services\Files\NullVirusScannerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NullVirusScannerServiceTest extends TestCase
{
    public function testDisabledScannerReturnsSafeAndLogsThatTheFileWasNotScanned(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Virus scanning is disabled; file was not scanned.',
                ['file' => '/tmp/example.pdf'],
            );

        $scanner = new NullVirusScannerService($logger);

        $this->assertTrue($scanner->isSafe('/tmp/example.pdf'));
    }

    public function testEnabledScannerFailsClosedAndLogsThatTheFileWasNotScanned(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Virus scanning was requested, but no scanner is configured; file was not scanned.',
                ['file' => '/tmp/example.pdf'],
            );

        $scanner = new NullVirusScannerService($logger, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(lang('Files.virus_scan_unavailable'));

        $scanner->isSafe('/tmp/example.pdf');
    }
}
