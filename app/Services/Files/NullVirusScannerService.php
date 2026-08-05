<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Interfaces\Files\VirusScannerServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Explicit no-op virus scanner used until a real scanner is integrated.
 *
 * Scanning disabled is an explicit configuration choice. Enabling the flag
 * without a scanner must fail closed instead of reporting a simulated scan.
 */
readonly class NullVirusScannerService implements VirusScannerServiceInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $enabled = false,
    ) {
    }

    public function isSafe(string $filePath): bool
    {
        if (! $this->enabled) {
            $this->logger->debug(
                'Virus scanning is disabled; file was not scanned.',
                ['file' => $filePath],
            );

            return true;
        }

        $this->logger->warning(
            'Virus scanning was requested, but no scanner is configured; file was not scanned.',
            ['file' => $filePath],
        );

        throw new \RuntimeException(lang('Files.virus_scan_unavailable'));
    }
}
