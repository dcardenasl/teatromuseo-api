<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes deployment hosts from persisted file URLs without touching files,
 * rows, paths, or any other file metadata.
 */
final class NormalizeFileUrls extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('files')->select('id, url, variants')->get()->getResultArray();

        foreach ($rows as $row) {
            $data = [];
            $url = $this->normalizeInternalUrl($row['url'] ?? null);
            if ($url !== ($row['url'] ?? null)) {
                $data['url'] = $url;
            }

            $variants = $this->decodeVariants($row['variants'] ?? null);
            $normalizedVariants = $this->normalizeVariants($variants);
            if ($normalizedVariants !== $variants) {
                $data['variants'] = json_encode($normalizedVariants, JSON_THROW_ON_ERROR);
            }

            if ($data !== []) {
                $this->db->table('files')->where('id', (int) $row['id'])->update($data);
            }
        }
    }

    public function down(): void
    {
        // Host information is intentionally not recoverable from portable data.
    }

    private function normalizeInternalUrl(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && str_contains($path, '/uploads/')) {
            return '/' . ltrim(substr($path, strpos($path, '/uploads/') + 1), '/');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function decodeVariants(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $variants @return array<string, mixed> */
    private function normalizeVariants(array $variants): array
    {
        foreach ($variants as $key => $variant) {
            if (! is_array($variant) || ! array_key_exists('url', $variant)) {
                continue;
            }
            $variants[$key]['url'] = $this->normalizeInternalUrl($variant['url']);
        }

        return $variants;
    }
}
