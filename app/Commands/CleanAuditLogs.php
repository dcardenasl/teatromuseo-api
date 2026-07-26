<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\AuditLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanAuditLogs extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'audit:clean';
    protected $description = 'Delete audit logs older than the configured retention window.';

    protected $usage = 'audit:clean [days]';
    protected $arguments = [
        'days' => 'Optional retention window in days. Defaults to AUDIT_RETENTION_DAYS or 90.',
    ];

    public function run(array $params): void
    {
        $configuredDays = (int) env('AUDIT_RETENTION_DAYS', 90);
        $retentionDays = isset($params[0]) ? (int) $params[0] : $configuredDays;
        $retentionDays = max(1, $retentionDays);

        $cutoffTimestamp = strtotime("-{$retentionDays} days");
        if ($cutoffTimestamp === false) {
            CLI::error("Could not compute a cutoff date for retention window: {$retentionDays} days.");
            return;
        }
        $cutoff = date('Y-m-d H:i:s', $cutoffTimestamp);

        $model = model(AuditLogModel::class);
        $deleted = $model->where('created_at <', $cutoff)->delete();

        $count = is_numeric($deleted) ? (int) $deleted : 0;
        CLI::write("Audit cleanup complete. Retention: {$retentionDays} days. Deleted rows: {$count}. Cutoff: {$cutoff}", 'green');
    }
}
