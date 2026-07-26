<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCodeToApplicationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('applications', [
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'default'    => '',
                'after'      => 'id',
            ],
        ]);

        // Backfill code from name (lowercased) for any existing rows.
        $rows = $this->db->table('applications')->select('id, name')->get()?->getResultArray() ?? [];
        foreach ($rows as $row) {
            $this->db->table('applications')
                ->where('id', (int) $row['id'])
                ->update(['code' => strtolower((string) $row['name'])]);
        }

        $this->forge->addKey('code', false, true, 'uniq_applications_code');
        $this->forge->processIndexes('applications');
    }

    public function down(): void
    {
        $this->forge->dropKey('applications', 'uniq_applications_code');
        $this->forge->dropColumn('applications', 'code');
    }
}
