<?php

declare(strict_types=1);

namespace App\Repositories\Common;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;
use dcardenasl\Ci4ApiCore\Repositories\PivotRepositoryInterface;

/**
 * Base implementation for pivot tables that link a parent resource to another
 * resource (typically the shared `files` table).
 *
 * Concrete repositories declare the parent FK column (e.g. `show_id`) by
 * implementing `getParentKey()`. The model itself is supplied via the standard
 * `BaseRepository` constructor, so that wiring stays consistent with the rest
 * of the repository layer.
 *
 * @extends BaseRepository<object>
 */
abstract class PivotRepository extends BaseRepository implements PivotRepositoryInterface
{
    public function __construct(Model $model)
    {
        parent::__construct($model);
    }

    /**
     * Name of the foreign key column that points back to the parent resource.
     * Concrete repositories return their domain-specific column name here.
     */
    abstract public function getParentKey(): string;

    public function findByParent(int $parentId): array
    {
        /** @var list<object> $rows */
        $rows = $this->model
            ->where($this->getParentKey(), $parentId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return $rows;
    }

    public function maxSortOrder(int $parentId): int
    {
        $row = $this->model
            ->selectMax('sort_order', 'max_order')
            ->where($this->getParentKey(), $parentId)
            ->first();

        if ($row === null) {
            return 0;
        }

        $raw = is_array($row) ? ($row['max_order'] ?? null) : ($row->max_order ?? null);

        return is_numeric($raw) ? (int) $raw : 0;
    }
}
