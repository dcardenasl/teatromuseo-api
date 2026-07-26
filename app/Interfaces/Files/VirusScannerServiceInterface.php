<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

/**
 * Virus Scanner Interface
 *
 * Contract for scanning files for malware/viruses.
 */
interface VirusScannerServiceInterface
{
    /**
     * Scan a file for viruses
     *
     * @param string $filePath Absolute path to the file
     * @return bool True if the file is clean (safe), false if a virus is detected
     * @throws \RuntimeException If the scanner is unavailable or fails to read the file
     */
    public function isSafe(string $filePath): bool;
}
