<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfills user_roles from the legacy (membership_roles, app_user_memberships) pair.
 * Idempotent: skips pairs that already exist in user_roles. Portable across drivers.
 */
class MigrateMembershipRolesToUserRoles extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('membership_roles') || ! $this->db->tableExists('app_user_memberships')) {
            return;
        }

        $rows = $this->db->table('membership_roles mr')
            ->select('m.user_id, mr.role_id, m.accepted_at, m.created_at')
            ->join('app_user_memberships m', 'm.id = mr.membership_id')
            ->get()
            ?->getResultArray() ?? [];

        if ($rows === []) {
            return;
        }

        $existing = $this->db->table('user_roles')
            ->select('user_id, role_id')
            ->get()
            ?->getResultArray() ?? [];

        $existingPairs = [];
        foreach ($existing as $row) {
            $existingPairs[((int) $row['user_id']) . '_' . ((int) $row['role_id'])] = true;
        }

        $batch = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $key = ((int) $row['user_id']) . '_' . ((int) $row['role_id']);
            if (isset($existingPairs[$key])) {
                continue;
            }

            $batch[] = [
                'user_id'             => (int) $row['user_id'],
                'role_id'             => (int) $row['role_id'],
                'assigned_at'         => $row['accepted_at'] ?? ($row['created_at'] ?? $now),
                'assigned_by_user_id' => null,
            ];
            $existingPairs[$key] = true;
        }

        if ($batch === []) {
            return;
        }

        // Wrap the batch insert in a transaction so a partial failure doesn't
        // leave user_roles with half the legacy data while migrate continues.
        $this->db->transStart();
        $this->db->table('user_roles')->insertBatch($batch);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException(
                'Failed to backfill user_roles from membership_roles. The transaction was rolled back. '
                . 'Inspect the logs and re-run "php spark migrate" once the underlying issue is resolved.'
            );
        }
    }

    public function down(): void
    {
        // No-op: forward-only data migration. Roll back via the legacy data still in membership_roles.
    }
}
