<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use CodeIgniter\Cache\CacheInterface;

/**
 * Versioned cache for the picker read model.
 *
 * A version key avoids trying to enumerate cache keys when a file mutation
 * changes the visible library. Old entries expire normally and become
 * unreachable immediately after the version is bumped.
 */
final class FilePickerManifestCache
{
    private const VERSION_KEY = 'files_picker_manifest_version';
    private const MANIFEST_TTL = 300;
    private const VERSION_TTL = 31_536_000;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * @param callable(): array<string, mixed> $loader
     * @return array<string, mixed>
     */
    public function remember(int $userId, bool $allFiles, callable $loader): array
    {
        $version = $this->version();
        $key = sprintf('files_picker_manifest_%s_%d_%d', $version, $userId, $allFiles ? 1 : 0);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $manifest = $loader();
        $this->cache->save($key, $manifest, self::MANIFEST_TTL);

        return $manifest;
    }

    public function invalidate(): void
    {
        $this->cache->save(self::VERSION_KEY, $this->version() + 1, self::VERSION_TTL);
    }

    public function currentVersion(): int
    {
        return $this->version();
    }

    private function version(): int
    {
        $version = $this->cache->get(self::VERSION_KEY);
        if (is_int($version) || (is_string($version) && ctype_digit($version))) {
            return (int) $version;
        }

        $this->cache->save(self::VERSION_KEY, 1, self::VERSION_TTL);

        return 1;
    }
}
