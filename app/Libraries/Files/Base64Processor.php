<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Support\Files\ProcessedFile;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class Base64Processor implements FileProcessorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function process(mixed $input, array $options = []): ProcessedFile
    {
        if (!is_string($input) || str_contains($input, 'Resource id #')) {
            throw new BadRequestException(lang('Files.invalid_file_object'));
        }

        // Detect Data URI or Raw Base64
        if (preg_match('/^data:(\w+\/[-+.\w]+);base64,(.+)$/', $input, $matches)) {
            $mimeType = $matches[1];
            $base64Data = $matches[2];
        } else {
            $base64Data = $input;
            $mimeType = $options['mimeType'] ?? 'application/octet-stream';
        }

        $estimatedSize = $this->estimateDecodedSize($base64Data);
        $this->validateSize($estimatedSize, $options);

        $contents = base64_decode($base64Data, true);
        if ($contents === false) {
            throw new BadRequestException(lang('Files.invalid_file_object'));
        }

        if ($mimeType === 'application/octet-stream') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);
        }

        $size = strlen($contents);
        $this->validateFileType($mimeType, $options);

        $extension = \Config\Mimes::guessExtensionFromType($mimeType) ?? 'bin';
        $originalName = $options['filename'] ?? ('file.' . $extension);

        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw new \RuntimeException(lang('Files.storage_error'));
        }

        fwrite($stream, $contents);
        rewind($stream);

        return new ProcessedFile(
            originalName: $originalName,
            mimeType: $mimeType,
            size: $size,
            extension: $extension,
            contents: $stream
        );
    }

    private function estimateDecodedSize(string $base64Data): int
    {
        $length = strlen($base64Data);
        $padding = 0;

        if ($length > 0 && substr($base64Data, -1) === '=') {
            $padding++;
        }

        if ($length > 1 && substr($base64Data, -2, 1) === '=') {
            $padding++;
        }

        return max(0, intdiv($length * 3, 4) - $padding);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validateSize(int $size, array $options): void
    {
        $apiConfig = config('Api');
        $maxSize = $options['maxSize'] ?? $apiConfig->fileMaxSize;
        if ($size > $maxSize) {
            throw new ValidationException(lang('Files.file_too_large'), ['file' => lang('Files.file_too_large')]);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validateFileType(string $mimeType, array $options): void
    {
        $apiConfig = config('Api');
        $extension = \Config\Mimes::guessExtensionFromType($mimeType) ?? 'bin';
        $allowedTypes = $options['allowedTypes'] ?? explode(',', $apiConfig->fileAllowedTypes);
        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            throw new ValidationException(lang('Files.invalid_file_type'), ['file' => lang('Files.invalid_file_type')]);
        }
    }
}
