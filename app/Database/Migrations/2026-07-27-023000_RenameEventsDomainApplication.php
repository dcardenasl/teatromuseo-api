<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aligns the event domain application code with the domain name used by the
 * monorepo. Resource/table names remain plural (`events`).
 */
class RenameEventsDomainApplication extends Migration
{
    public function up(): void
    {
        $legacy = $this->db->table('applications')
            ->where('code', 'events')
            ->get()
            ->getRowArray();
        $current = $this->db->table('applications')
            ->where('code', 'event')
            ->get()
            ->getRowArray();

        if ($legacy !== null && $current === null) {
            $this->db->table('applications')
                ->where('id', (int) $legacy['id'])
                ->update([
                    'code'       => 'event',
                    'name'       => 'Event',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down(): void
    {
        $current = $this->db->table('applications')
            ->where('code', 'event')
            ->get()
            ->getRowArray();
        $legacy = $this->db->table('applications')
            ->where('code', 'events')
            ->get()
            ->getRowArray();

        if ($current !== null && $legacy === null) {
            $this->db->table('applications')
                ->where('id', (int) $current['id'])
                ->update([
                    'code'       => 'events',
                    'name'       => 'Events',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }
}
