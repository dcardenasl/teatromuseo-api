<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use App\Models\FileModel;
use App\Models\RequestLogModel;
use App\Models\UserModel;

final class DashboardSummaryRepository implements DashboardSummaryRepositoryInterface
{
    public function __construct(
        private readonly UserModel $users,
        private readonly FileModel $files,
        private readonly RequestLogModel $requestLogs,
    ) {
    }

    /**
     * Keep the dashboard projection small and bounded. This is intentionally
     * not implemented by calling the CRUD services or their HTTP endpoints.
     *
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $recentFiles = $this->files
            ->select('id, original_name, stored_name, mime_type, category, size, url, variants, width, height, uploaded_at')
            ->orderBy('uploaded_at', 'DESC')
            ->findAll(5);

        return [
            'users_total' => (int) $this->users->countAllResults(),
            'files_total' => (int) $this->files->countAllResults(),
            'recent_files' => array_map(
                fn (mixed $file): array => $this->fileToArray($file),
                $recentFiles
            ),
            'metrics' => [
                'request_stats' => $this->requestLogs->getStats('24h'),
                'slow_requests' => $this->requestLogs->getSlowRequests(1000, 5, '24h'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileToArray(mixed $file): array
    {
        $data = is_object($file) && method_exists($file, 'toArray')
            ? $file->toArray()
            : (is_array($file) ? $file : []);

        $variants = $data['variants'] ?? null;
        if (is_string($variants)) {
            $decoded = json_decode($variants, true);
            $variants = is_array($decoded) ? $decoded : null;
        }

        $size = (int) ($data['size'] ?? 0);
        $mimeType = (string) ($data['mime_type'] ?? '');

        return [
            'id' => (int) ($data['id'] ?? 0),
            'original_name' => (string) ($data['original_name'] ?? ''),
            'stored_name' => (string) ($data['stored_name'] ?? ''),
            'mime_type' => $mimeType,
            'category' => (string) ($data['category'] ?? ''),
            'size' => $size,
            'human_size' => $this->humanSize($size),
            'is_image' => str_starts_with($mimeType, 'image/'),
            'url' => (string) ($data['url'] ?? ''),
            'uploaded_at' => isset($data['uploaded_at']) ? (string) $data['uploaded_at'] : null,
            'variants' => is_array($variants) ? $variants : null,
            'width' => isset($data['width']) ? (int) $data['width'] : null,
            'height' => isset($data['height']) ? (int) $data['height'] : null,
        ];
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $index = 0;

        while ($value > 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 2) . ' ' . $units[$index];
    }
}
