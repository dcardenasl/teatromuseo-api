<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseResult;
use RuntimeException;

class RequestLogModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    protected $table = 'request_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'method',
        'uri',
        'user_id',
        'ip_address',
        'user_agent',
        'response_code',
        'response_time',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Get request statistics
     *
     * @param string $period (hour, day, week, month)
     * @return array<string, mixed>
     */
    public function getStats(string $period = 'day'): array
    {
        $since = $this->getSinceFromPeriod($period);

        // Keep all scalar counters in one database aggregation. The dashboard
        // must not count the same time window with a separate PHP/SQL round
        // trip for every status bucket.
        $aggregate = $this->db->query(
            'SELECT COUNT(*) AS total_requests,
                    SUM(CASE WHEN response_code >= 200 AND response_code < 400 THEN 1 ELSE 0 END) AS successful_requests,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) AS failed_requests,
                    AVG(response_time) AS avg_response_time,
                    SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) AS status_2xx,
                    SUM(CASE WHEN response_code >= 300 AND response_code < 400 THEN 1 ELSE 0 END) AS status_3xx,
                    SUM(CASE WHEN response_code >= 400 AND response_code < 500 THEN 1 ELSE 0 END) AS status_4xx,
                    SUM(CASE WHEN response_code >= 500 AND response_code < 600 THEN 1 ELSE 0 END) AS status_5xx
             FROM ' . $this->table . '
             WHERE created_at >= ?',
            [$since]
        );
        if (! $aggregate instanceof BaseResult) {
            throw new RuntimeException('Request statistics aggregate query failed.');
        }

        $aggregateRow = $aggregate->getRowArray();
        $totalRequests = (int) ($aggregateRow['total_requests'] ?? 0);
        $successfulRequests = (int) ($aggregateRow['successful_requests'] ?? 0);
        $failedRequests = (int) ($aggregateRow['failed_requests'] ?? 0);
        $avgResponseTime = (float) ($aggregateRow['avg_response_time'] ?? 0);

        // Optimized Percentile Calculation (O(1) Memory)
        [$p95, $p99] = $this->getPercentilesFromDb($since, $totalRequests);

        $errorRate = $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0.0;
        $availability = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 100.0;
        $latencyTarget = config('Api')->sloP95TargetMs ?? 500;

        return [
            'period' => $period,
            'since' => $since,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'p95_response_time_ms' => $p95,
            'p99_response_time_ms' => $p99,
            'error_rate_percent' => round($errorRate, 2),
            'availability_percent' => round($availability, 2),
            'status_code_breakdown' => [
                '2xx' => (int) ($aggregateRow['status_2xx'] ?? 0),
                '3xx' => (int) ($aggregateRow['status_3xx'] ?? 0),
                '4xx' => (int) ($aggregateRow['status_4xx'] ?? 0),
                '5xx' => (int) ($aggregateRow['status_5xx'] ?? 0),
            ],
            'slo' => [
                'p95_target_ms' => $latencyTarget,
                'p95_target_met' => $p95 <= $latencyTarget,
            ],
        ];
    }

    /**
     * Calculate both percentiles in one ordered database projection.
     *
     * The rank intentionally preserves the previous LIMIT/OFFSET semantics:
     * floor(percentile * count) is the zero-based offset.
     *
     * @return array{0: float, 1: float}
     */
    private function getPercentilesFromDb(string $since, int $totalCount): array
    {
        if ($totalCount === 0) {
            return [0.0, 0.0];
        }

        $query = $this->db->query(
            'SELECT MAX(CASE WHEN row_number_value = FLOOR(total_count * 0.95) + 1 THEN response_time END) AS p95,
                    MAX(CASE WHEN row_number_value = FLOOR(total_count * 0.99) + 1 THEN response_time END) AS p99
             FROM (
                 SELECT id, response_time,
                        ROW_NUMBER() OVER (ORDER BY response_time ASC, id ASC) AS row_number_value,
                        COUNT(*) OVER () AS total_count
                 FROM ' . $this->table . '
                 WHERE created_at >= ?
             ) ranked',
            [$since]
        );
        if (! $query instanceof BaseResult) {
            throw new RuntimeException('Request statistics percentile query failed.');
        }

        $row = $query->getRowArray();

        return [
            (float) ($row['p95'] ?? 0),
            (float) ($row['p99'] ?? 0),
        ];
    }

    /**
     * Get slow requests
     *
     * @param int $threshold Threshold in milliseconds
     * @param int $limit
     * @return array<int, array<int|string, bool|float|int|object|string|null>|object>
     */
    public function getSlowRequests(int $threshold = 1000, int $limit = 10): array
    {
        return $this->select('method, uri, response_time, created_at')
            ->where('response_time >', $threshold)
            ->orderBy('response_time', 'DESC')
            ->limit($limit)
            ->find();
    }

    private function getSinceFromPeriod(string $period): string
    {
        return match ($period) {
            '1h' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            '7d' => date('Y-m-d H:i:s', strtotime('-7 days')),
            '30d' => date('Y-m-d H:i:s', strtotime('-30 days')),
            default => date('Y-m-d H:i:s', strtotime('-24 hours')), // '24h' and unrecognized values
        };
    }

    /**
     * Get time-bucketed request volume, error count, and average latency —
     * one point per bucket, gaps filled with zeros so charts don't show
     * misleadingly sparse data.
     *
     * @return array{dates: list<string>, requests: list<int>, errors: list<int>, latency: list<float>}
     */
    public function getTimeseries(string $period = '24h'): array
    {
        [$bucketSeconds, $bucketCount, $labelFormat] = $this->resolveBucketConfig($period);
        $since = date('Y-m-d H:i:s', time() - ($bucketSeconds * $bucketCount));

        $query = $this->db->table($this->table)
            ->select(sprintf(
                'FLOOR(UNIX_TIMESTAMP(created_at) / %1$d) * %1$d as bucket_ts,'
                . ' COUNT(*) as total,'
                . ' SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as errors,'
                . ' AVG(response_time) as avg_latency',
                $bucketSeconds
            ), false)
            ->where('created_at >=', $since)
            ->groupBy('bucket_ts')
            ->orderBy('bucket_ts', 'ASC')
            ->get();

        $rows = $query ? $query->getResultArray() : [];

        $byBucket = [];
        foreach ($rows as $row) {
            $byBucket[(int) $row['bucket_ts']] = $row;
        }

        $lastBucket = (int) (floor(time() / $bucketSeconds) * $bucketSeconds);
        $firstBucket = $lastBucket - (($bucketCount - 1) * $bucketSeconds);

        $dates = [];
        $requests = [];
        $errors = [];
        $latency = [];

        for ($ts = $firstBucket; $ts <= $lastBucket; $ts += $bucketSeconds) {
            $row = $byBucket[$ts] ?? null;

            $dates[] = date($labelFormat, $ts);
            $requests[] = $row ? (int) $row['total'] : 0;
            $errors[] = $row ? (int) $row['errors'] : 0;
            $latency[] = $row ? round((float) $row['avg_latency'], 2) : 0.0;
        }

        return [
            'dates' => $dates,
            'requests' => $requests,
            'errors' => $errors,
            'latency' => $latency,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function resolveBucketConfig(string $period): array
    {
        return match ($period) {
            '1h' => [300, 12, 'H:i'],
            '7d' => [86400, 7, 'Y-m-d'],
            '30d' => [86400, 30, 'Y-m-d'],
            default => [3600, 24, 'Y-m-d H:00'], // '24h' and unrecognized values
        };
    }

}
