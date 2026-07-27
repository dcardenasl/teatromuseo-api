<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Small seam around cross-domain HTTP calls. The migration service can be
 * tested with a fake client while production uses the real CI4 client.
 */
interface LegacyDomainClientInterface
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array;

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function upload(string $path, string $filePath, string $filename, array $fields = []): array;
}
