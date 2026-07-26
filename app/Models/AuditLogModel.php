<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

/**
 * Audit Log Model
 *
 * Stores audit trail of all data changes
 */
class AuditLogModel extends Model
{
    use Filterable;
    use Searchable;

    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = \App\Entities\AuditLogEntity::class;
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'result',
        'severity',
        'request_id',
        'metadata',
        'created_at',
    ];

    // No timestamps (using custom created_at)
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';

    /**
     * Validation rules
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'action' => 'required|max_length[50]',
        'entity_type' => 'required|max_length[50]',
        'ip_address' => 'required|max_length[45]',
        'result' => 'permit_empty|in_list[success,failure,denied]',
        'severity' => 'permit_empty|in_list[info,warning,critical]',
        'request_id' => 'permit_empty|max_length[64]',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Filtering and searching
    /** @var array<int, string> */
    protected array $filterableFields = ['user_id', 'action', 'entity_type', 'entity_id', 'result', 'severity', 'request_id', 'created_at'];

    /** @var array<int, string> */
    protected array $searchableFields = ['action', 'entity_type', 'request_id'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'result', 'severity', 'created_at'];

    /**
     * Get audit logs for an entity
     *
     * @param string $entityType Entity type (e.g., 'user', 'file')
     * @param int $entityId Entity ID
     * @return array<int, mixed>
     */
    public function getByEntity(string $entityType, int $entityId): array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Get audit logs for a user
     *
     * @param int $userId
     * @param int $limit
     * @return array<int, mixed>
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    /**
     * Get recent audit logs
     *
     * @param int $limit
     * @return array<int, mixed>
     */
    public function getRecent(int $limit = 100): array
    {
        return $this->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    /**
     * @return array<int, array{value:string,count:int}>
     */
    public function getActionFacets(int $windowDays = 90, int $limit = 100): array
    {
        $ts    = strtotime('-' . max(1, $windowDays) . ' days');
        $since = date('Y-m-d H:i:s', $ts !== false ? $ts : time());
        $query = $this->builder()
            ->select('action AS value, COUNT(*) AS count')
            ->where('created_at >=', $since)
            ->where('action IS NOT NULL', null, false)
            ->where("TRIM(action) != ''", null, false)
            ->groupBy('action')
            ->orderBy('count', 'DESC')
            ->orderBy('action', 'ASC')
            ->limit(max(1, min($limit, 500)))
            ->get();

        $rows = $query ? $query->getResultArray() : [];

        return array_map(static fn (array $row): array => [
            'value' => (string) ($row['value'] ?? ''),
            'count' => (int) ($row['count'] ?? 0),
        ], $rows);
    }

    /**
     * @return array<int, array{value:string,count:int}>
     */
    public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array
    {
        $ts    = strtotime('-' . max(1, $windowDays) . ' days');
        $since = date('Y-m-d H:i:s', $ts !== false ? $ts : time());
        $query = $this->builder()
            ->select('entity_type AS value, COUNT(*) AS count')
            ->where('created_at >=', $since)
            ->where('entity_type IS NOT NULL', null, false)
            ->where("TRIM(entity_type) != ''", null, false)
            ->groupBy('entity_type')
            ->orderBy('count', 'DESC')
            ->orderBy('entity_type', 'ASC')
            ->limit(max(1, min($limit, 500)))
            ->get();

        $rows = $query ? $query->getResultArray() : [];

        return array_map(static fn (array $row): array => [
            'value' => (string) ($row['value'] ?? ''),
            'count' => (int) ($row['count'] ?? 0),
        ], $rows);
    }
}
