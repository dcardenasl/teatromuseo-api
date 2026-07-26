<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Support\Files\ProcessedFile;

/**
 * Interface for file input processors.
 */
interface FileProcessorInterface
{
    /**
     * Process the input data into a standardized ProcessedFile object.
     *
     * @param mixed $input Raw input (UploadedFile or Base64 string)
     * @param array<string, mixed> $options Additional options (mimetypes, max size, etc.)
     */
    public function process(mixed $input, array $options = []): ProcessedFile;
}
