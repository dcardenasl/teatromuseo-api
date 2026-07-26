<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use CodeIgniter\HTTP\Files\UploadedFile;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

/**
 * File Upload Request DTO
 *
 * Validates the uploaded file object (Multipart or Base64) and user ownership.
 */
readonly class FileUploadRequestDTO extends BaseRequestDTO
{
    public UploadedFile|string $file;
    public int $user_id;
    public ?string $filename;
    public ?string $visibility;

    public function rules(): array
    {
        return []; // Custom validation handled in map() due to complex file logic
    }

    protected function map(array $data): void
    {
        log_message('debug', '[FileUploadRequestDTO] payload: ' . json_encode($this->preparePayloadForLog($data)));
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->user_id = (int) $data['user_id'];
        $this->filename = $data['filename'] ?? null;
        $this->visibility = isset($data['visibility']) && is_string($data['visibility'])
            ? strtolower(trim($data['visibility']))
            : null;

        $fileData = $this->extractFileFromData($data);

        if ($fileData === null) {
            throw new BadRequestException(lang('Api.invalidRequest'), [
                'file' => lang('Files.upload.noFile')
            ]);
        }

        $this->file = $fileData;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractFileFromData(array $data): UploadedFile|string|null
    {
        // 1. Prioritize 'file' key
        if (isset($data['file'])) {
            $file = $data['file'];
            if ($file instanceof UploadedFile) {
                return $file;
            }
            if (is_array($file) && $this->isFileArray($file)) {
                return $this->createUploadedFileFromArray($file);
            }
            if (is_string($file) && (str_starts_with($file, 'data:') || strlen($file) > 100)) {
                return $file;
            }
        }

        // 2. Look for any UploadedFile object in payload
        if (($file = $this->findUploadedFileInArray($data)) !== null) {
            return $file;
        }

        // 3. Fallback: Search for potential Base64 or large strings in other keys
        foreach ($data as $key => $value) {
            if (in_array($key, ['user_id', 'filename', 'visibility'], true)) {
                continue;
            }

            if (is_string($value) && (str_starts_with($value, 'data:') || strlen($value) > 1000)) {
                return $value;
            }
            if (is_array($value) && $this->isFileArray($value)) {
                return $this->createUploadedFileFromArray($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findUploadedFileInArray(array $data): ?UploadedFile
    {
        foreach ($data as $value) {
            if ($value instanceof UploadedFile) {
                return $value;
            }

            if (is_array($value)) {
                if ($this->isFileArray($value)) {
                    return $this->createUploadedFileFromArray($value);
                }

                $nested = $this->findUploadedFileInArray($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isFileArray(array $value): bool
    {
        return isset($value['tmp_name'], $value['name']);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function createUploadedFileFromArray(array $value): UploadedFile
    {
        return new UploadedFile(
            $value['tmp_name'],
            $value['name'],
            $value['type'] ?? null,
            isset($value['size']) ? (int) $value['size'] : null,
            isset($value['error']) ? (int) $value['error'] : null,
            $value['full_path'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function preparePayloadForLog(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->sanitizeValueForLog($key, $value);
        }
        return $result;
    }

    private function sanitizeValueForLog(int|string $key, mixed $value): mixed
    {
        if (is_string($key) && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getName(),
                'size' => $value->getSize(),
                'mimeType' => $value->getMimeType(),
            ];
        }

        if (is_array($value)) {
            return $this->preparePayloadForLog($value);
        }

        if (is_string($value)) {
            $length = strlen($value);
            if (str_starts_with($value, 'data:') || $length > 256) {
                return "[REDACTED length={$length}]";
            }
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return false;
        }

        return preg_match(
            '/(^|_)(password|token|secret|api_?key|key_?hash|private_?key|access_?token|refresh_?token|verification_?token)($|_)/i',
            $normalized
        ) === 1;
    }

    public function isBase64(): bool
    {
        return is_string($this->file);
    }

    public function toArray(): array
    {
        return [
            'file'     => $this->file,
            'user_id'   => $this->user_id,
            'filename' => $this->filename,
            'visibility' => $this->visibility,
        ];
    }
}
