<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Resolves a legacy public path without copying or mutating the asset.
 *
 * Importing files belongs to the target file service. This seam only turns a
 * legacy path into a safe, hashable source descriptor that can be recorded in
 * the migration report and handed to that service later.
 */
final class LegacyAssetResolver
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $resolvedRoot = realpath($rootPath);
        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new \InvalidArgumentException("Legacy asset root '{$rootPath}' does not exist.");
        }

        $this->rootPath = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{
     *     status: 'resolved'|'missing'|'invalid',
     *     source_path: string,
     *     relative_path: string|null,
     *     absolute_path: string|null,
     *     sha256: string|null,
     *     size: int|null,
     *     mime_type: string|null,
     *     reason: string|null
     * }
     */
    public function resolve(string $legacyPath): array
    {
        $sourcePath = trim($legacyPath);
        if ($sourcePath === '') {
            return $this->invalid($sourcePath, 'empty_path');
        }

        // parse_url() is byte-oriented, not UTF-8-aware: on some legacy paths it strips or
        // corrupts a UTF-8 continuation byte that happens to match an ASCII control character
        // it treats specially (e.g. 0xAD, the second byte of "í"'s 2-byte encoding, gets turned
        // into "_"). Only route through it when the string actually looks like a full URL
        // (has a scheme) — legacy paths are already bare filesystem paths and don't need it.
        if (str_contains($sourcePath, '://')) {
            $parsed = parse_url($sourcePath, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : $sourcePath;
        } else {
            $path = $sourcePath;
        }
        $path = str_replace('\\', '/', $path);
        $relativePath = ltrim($path, '/');
        $segments = explode('/', $relativePath);
        if ($relativePath === '' || in_array('..', $segments, true) || in_array('', $segments, true)) {
            return $this->invalid($path, 'unsafe_path');
        }

        $candidate = realpath($this->rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($candidate === false) {
            return [
                'status'        => 'missing',
                'source_path'   => $path,
                'relative_path' => $relativePath,
                'absolute_path' => null,
                'sha256'        => null,
                'size'          => null,
                'mime_type'     => null,
                'reason'        => 'file_not_found',
            ];
        }

        if (! is_file($candidate) || ! $this->isInsideRoot($candidate)) {
            return $this->invalid($path, 'path_outside_asset_root');
        }

        $sha256 = hash_file('sha256', $candidate);
        $size = filesize($candidate);
        if ($sha256 === false || $size === false) {
            return $this->invalid($path, 'file_metadata_unavailable');
        }

        return [
            'status'        => 'resolved',
            'source_path'   => $path,
            'relative_path' => $relativePath,
            'absolute_path' => $candidate,
            'sha256'        => $sha256,
            'size'          => $size,
            'mime_type'     => $this->mimeType($candidate),
            'reason'        => null,
        ];
    }

    /** @return array{status: 'invalid', source_path: string, relative_path: null, absolute_path: null, sha256: null, size: null, mime_type: null, reason: string} */
    private function invalid(string $sourcePath, string $reason): array
    {
        return [
            'status'        => 'invalid',
            'source_path'   => $sourcePath,
            'relative_path' => null,
            'absolute_path' => null,
            'sha256'        => null,
            'size'          => null,
            'mime_type'     => null,
            'reason'        => $reason,
        ];
    }

    private function isInsideRoot(string $candidate): bool
    {
        return $candidate === $this->rootPath
            || str_starts_with($candidate, $this->rootPath . DIRECTORY_SEPARATOR);
    }

    private function mimeType(string $path): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType === false ? null : $mimeType;
    }
}
