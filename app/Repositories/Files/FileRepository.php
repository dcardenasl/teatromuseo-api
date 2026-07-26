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
}
