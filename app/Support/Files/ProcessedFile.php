<?php

declare(strict_types=1);

namespace App\Support\Files;

/**
 * Processed File
 *
 * A value object representing a file that has been validated and read into a stream,
 * ready to be stored.
 */
class ProcessedFile
{
    /**
     * @param resource $contents The file contents as a PHP resource (stream)
     */
    public function __construct(
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $extension,
        public $contents
    ) {
        if (!is_resource($this->contents)) {
            throw new \InvalidArgumentException('Contents must be a valid PHP resource.');
        }
    }

    /**
     * Close the internal stream if it's still open.
     */
    public function __destruct()
    {
        if (is_resource($this->contents)) {
            fclose($this->contents);
        }
    }
}
