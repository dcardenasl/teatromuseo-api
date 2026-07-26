<?php

declare(strict_types=1);

namespace App\Repositories\System;

use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;

/**
 * Audit Repository Implementation
 *
 * @extends BaseRepository<object>
 */
class AuditRepository extends BaseRepository implements AuditRepositoryInterface
{
    public function getByEntity(string $entityType, int $entityId): array
    {
        /** @var list<object> $rows */
        $rows = $this->model->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'DESC')
            ->findAll();

        return $rows;
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        /** @var list<object> $rows */
        $rows = $this->model->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);

        return $rows;
    }

    public function getRecent(int $limit = 100): array
    {
        /** @var list<object> $rows */
        $rows = $this->model->orderBy('created_at', 'DESC')
            ->findAll($limit);

        return $rows;
    }

    public function getActionFacets(int $windowDays = 90, int $limit = 100): array
    {
        /** @var \App\Models\AuditLogModel $model */
        $model = $this->model;
        /** @var list<array{value: string, count: int}> $facets */
        $facets = array_values($model->getActionFacets($windowDays, $limit));

        return $facets;
    }

    public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array
    {
        /** @var \App\Models\AuditLogModel $model */
        $model = $this->model;
        /** @var list<array{value: string, count: int}> $facets */
        $facets = array_values($model->getEntityTypeFacets($windowDays, $limit));

        return $facets;
    }
}
