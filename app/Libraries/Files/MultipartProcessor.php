<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Support\Files\ProcessedFile;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Mimes;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class MultipartProcessor implements FileProcessorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function process(mixed $input, array $options = []): ProcessedFile
    {
        if (!$input instanceof UploadedFile) {
            throw new BadRequestException(lang('Files.invalid_file_object'));
        }

        if (!$input->isValid()) {
            throw new BadRequestException(lang('Files.upload_failed', [$input->getErrorString()]));
        }

        $this->validate($input, $options);

        $stream = fopen($input->getTempName(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException(lang('Files.storage_error'));
        }

        return new ProcessedFile(
            originalName: $input->getName(),
            mimeType: $input->getMimeType(),
            size: (int) $input->getSize(),
            extension: $input->getExtension(),
            contents: $stream
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validate(UploadedFile $file, array $options): void
    {
        $apiConfig = config('Api');
        $maxSize = $options['maxSize'] ?? $apiConfig->fileMaxSize;
        if ($file->getSize() > $maxSize) {
            throw new ValidationException(lang('Files.file_too_large'), ['file' => lang('Files.file_too_large')]);
        }

        $extension = strtolower($file->getExtension());

        $allowedTypes = $options['allowedTypes'] ?? explode(',', $apiConfig->fileAllowedTypes);
        if (!in_array($extension, $allowedTypes, true)) {
            throw new ValidationException(lang('Files.invalid_file_type'), ['file' => lang('Files.invalid_file_type')]);
        }

        // Cross-check the real (fileinfo-detected) mime type against the mimes
        // registered for the declared extension. Rejects spoofing such as a
        // ".jpg" whose real content is application/zip. When the extension is
        // unknown to the mime map we fall through — the allowlist above already
        // constrained it to known-safe extensions.
        $realMime = strtolower((string) $file->getMimeType());
        $expectedMimes = Mimes::$mimes[$extension] ?? null;
        if ($realMime !== '' && $expectedMimes !== null) {
            $expectedMimes = array_map('strtolower', (array) $expectedMimes);
            if (!in_array($realMime, $expectedMimes, true)) {
                log_message('warning', sprintf(
                    '[MultipartProcessor] MIME mismatch: extension=%s real=%s name=%s',
                    $extension,
                    $realMime,
                    $file->getName()
                ));

                throw new ValidationException(lang('Files.file_mime_mismatch'), ['file' => lang('Files.file_mime_mismatch')]);
            }
        }
    }
}
