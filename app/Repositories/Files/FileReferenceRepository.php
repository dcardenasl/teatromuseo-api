<?php

declare(strict_types=1);

namespace App\Repositories\Files;

use App\Interfaces\Files\FileReferenceRepositoryInterface;
use App\Models\Files\FileReferenceModel;

class FileReferenceRepository implements FileReferenceRepositoryInterface
{
    public function __construct(
        protected FileReferenceModel $model
    ) {
    }

    public function register(int $fileId, string $resourceType, int $resourceId, string $role, ?string $label = null): bool
    {
        $db = $this->model->db;

        /** @var \stdClass|null $existing */
        $existing = $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('role', $role)
            ->first();

        if ($existing !== null) {
            return (bool) $this->model->update($existing->id, [
                'file_id' => $fileId,
                'label'   => $label,
            ]);
        }

        return (bool) $this->model->insert([
            'file_id'       => $fileId,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'role'          => $role,
            'label'         => $label,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function unregisterByResource(string $resourceType, int $resourceId, string $role): bool
    {
        return (bool) $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('role', $role)
            ->delete();
    }

    public function getByFileId(int $fileId): array
    {
        /** @var list<\stdClass> $rows */
        $rows = $this->model->where('file_id', $fileId)->findAll();

        return array_map(fn ($row) => [
            'source'      => 'hub',
            'resource'    => $row->resource_type,
            'resource_id' => (int) $row->resource_id,
            'label'       => $row->label,
            'role'        => $row->role,
        ], $rows);
    }
}
