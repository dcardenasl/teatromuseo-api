<?php

declare(strict_types=1);

namespace App\Models;

class MetricModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    protected $table = 'metrics';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'metric_name',
        'metric_value',
        'tags',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Record a metric
     *
     * @param string $name
     * @param float $value
     * @param array<string, mixed> $tags
     * @return int|false
     */
    public function record(string $name, float $value, array $tags = [])
    {
        $id = $this->insert([
            'metric_name' => $name,
            'metric_value' => $value,
            'tags' => empty($tags) ? null : json_encode($tags),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return is_numeric($id) ? (int) $id : false;
    }

    /**
     * Get metrics by name
     *
     * @param string $name
     * @param string $period
     * @return array<int, array<int|string, bool|float|int|object|string|null>|object>
     */
    public function getByName(string $name, string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };

        return $this->where('metric_name', $name)
            ->where('created_at >=', $since)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get aggregated metrics
     *
     * @param string $name
     * @param string $period
     * @return array<string, mixed>
     */
    public function getAggregated(string $name, string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };

        $query = $this->builder()
            ->select('
                COUNT(*) as count,
                AVG(metric_value) as average,
                MIN(metric_value) as minimum,
                MAX(metric_value) as maximum,
                SUM(metric_value) as sum
            ')
            ->where('metric_name', $name)
            ->where('created_at >=', $since)
            ->get();

        $result = $query ? $query->getRow() : null;

        if (!$result) {
            return [
                'metric_name' => $name,
                'period' => $period,
                'count' => 0,
                'average' => 0.0,
                'minimum' => 0.0,
                'maximum' => 0.0,
                'sum' => 0.0,
            ];
        }

        return [
            'metric_name' => $name,
            'period' => $period,
            'count' => (int) ($result->count ?? 0),
            'average' => round((float) ($result->average ?? 0), 2),
            'minimum' => round((float) ($result->minimum ?? 0), 2),
            'maximum' => round((float) ($result->maximum ?? 0), 2),
            'sum' => round((float) ($result->sum ?? 0), 2),
        ];
    }
}
