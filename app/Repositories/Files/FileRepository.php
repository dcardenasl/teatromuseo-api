<?php

declare(strict_types=1);

namespace App\Repositories\Files;

use App\Entities\FileEntity;
use App\Interfaces\Files\FileRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;

/**
 * File Repository (Implementation)
 *
 * @extends BaseRepository<FileEntity>
 */
class FileRepository extends BaseRepository implements FileRepositoryInterface
{
    public function findByStoredName(string $storedName): ?object
    {
        /** @var FileEntity|null $file */
        $file = $this->model->where('stored_name', $storedName)->first();

        return $file;
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->model->where('user_id', $userId)->countAllResults();
    }

    public function findIncludingTrashed(int $id): ?object
    {
        /** @var FileEntity|null $result */
        $result = $this->model->withDeleted()->find($id);

        return $result;
    }

    public function purge(int $id): bool
    {
        return (bool) $this->model->delete($id, true);
    }

    public function restore(int|string $id, array $data = []): bool
    {
        $data['deleted_by_user_id'] = null;

        return parent::restore($id, $data);
    }

    public function findByUrl(string $url): ?object
    {
        /** @var FileEntity|null $result */
        $result = $this->model->withDeleted()->where('url', $url)->first();

        return $result;
    }

    /**
     * @param array<int|string, mixed> $ids
     * @return array<int, array{id: int, path: string, url: string|null, variants: mixed}>
     */
    public function findPublicMetaBatch(array $ids): array
    {
        $filteredIds = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($filteredIds)) {
            return [];
        }

        $filteredIds = array_slice($filteredIds, 0, 200);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->model
            ->select('id, path, url, variants')
            ->whereIn('id', $filteredIds)
            ->where('deleted_at IS NULL')
            ->asArray()
            ->findAll();

        $result = [];
        foreach ($rows as $row) {
            $idRaw = $row['id'] ?? 0;
            $fileId = is_scalar($idRaw) ? (int) $idRaw : 0;
            if ($fileId <= 0) {
                continue;
            }

            $path = is_scalar($row['path'] ?? null) ? trim((string) $row['path']) : '';
            $url = is_scalar($row['url'] ?? null) ? (string) $row['url'] : null;
            $variants = $row['variants'] ?? null;

            $result[$fileId] = [
                'id'       => $fileId,
                'path'     => $path,
                'url'      => $url,
                'variants' => $variants,
            ];
        }

        return $result;
    }
}
