<?php

declare(strict_types=1);

namespace App\Libraries\Files;

/**
 * Storage Key Generator
 *
 * Produces opaque, filesystem-safe object keys for persisted files.
 * The returned value is intentionally unrelated to the original filename so
 * storage paths remain stable, non-guessable, and collision-resistant.
 */
class StorageKeyGenerator
{
    /**
     * Generate an opaque basename for a stored file.
     *
     * The basename is derived from a short content hash prefix plus random
     * bytes. The caller is expected to prepend any date-based directory.
     */
    public function generate(string $extension, ?string $contentHash = null): string
    {
        $extension = $this->normalizeExtension($extension);
        $prefix = $this->hashPrefix($contentHash);
        $suffix = bin2hex(random_bytes(8));

        return sprintf('%s-%s.%s', $prefix, $suffix, $extension);
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        return $extension !== '' ? $extension : 'bin';
    }

    private function hashPrefix(?string $contentHash): string
    {
        if (is_string($contentHash) && preg_match('/^[a-f0-9]{64}$/i', $contentHash) === 1) {
            return substr(strtolower($contentHash), 0, 12);
        }

        return bin2hex(random_bytes(6));
    }
}
