<?php

declare(strict_types=1);

namespace App\Models;

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

        $totalRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->countAllResults();

        $successfulRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->where('response_code >=', 200)
            ->where('response_code <', 400)
            ->countAllResults();

        $failedRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->where('response_code >=', 400)
            ->countAllResults();

        $avgResponseTimeQuery = $this->db->table($this->table)
            ->select('AVG(response_time) as avg_response_time')
            ->where('created_at >=', $since)
            ->get();

        $avgResponseTimeRaw = $avgResponseTimeQuery ? $avgResponseTimeQuery->getRow() : null;
        $avgResponseTime = $avgResponseTimeRaw ? (float) ($avgResponseTimeRaw->avg_response_time ?? 0) : 0.0;

        // Optimized Percentile Calculation (O(1) Memory)
        $p95 = $this->getPercentileFromDb($since, $totalRequests, 0.95);
        $p99 = $this->getPercentileFromDb($since, $totalRequests, 0.99);

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
            'status_code_breakdown' => $this->getStatusCodeBreakdown($since),
            'slo' => [
                'p95_target_ms' => $latencyTarget,
                'p95_target_met' => $p95 <= $latencyTarget,
            ],
        ];
    }

    /**
     * Efficiently calculates a percentile value directly from the DB using LIMIT/OFFSET.
     */
    private function getPercentileFromDb(string $since, int $totalCount, float $percentile): float
    {
        if ($totalCount === 0) {
            return 0.0;
        }

        $offset = (int) floor($percentile * $totalCount);
        // Ensure offset is within bounds (0 to totalCount - 1)
        $offset = max(0, min($offset, $totalCount - 1));

        $query = $this->db->table($this->table)
            ->select('response_time')
            ->where('created_at >=', $since)
            ->orderBy('response_time', 'ASC')
            ->limit(1, $offset)
            ->get();

        $row = $query ? $query->getRow() : null;

        return $row ? (float) $row->response_time : 0.0;
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

    /**
     * @return array{'2xx':int,'3xx':int,'4xx':int,'5xx':int}
     */
    private function getStatusCodeBreakdown(string $since): array
    {
        return [
            '2xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 200)
                ->where('response_code <', 300)
                ->countAllResults(),
            '3xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 300)
                ->where('response_code <', 400)
                ->countAllResults(),
            '4xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 400)
                ->where('response_code <', 500)
                ->countAllResults(),
            '5xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 500)
                ->where('response_code <', 600)
                ->countAllResults(),
        ];
    }
}
