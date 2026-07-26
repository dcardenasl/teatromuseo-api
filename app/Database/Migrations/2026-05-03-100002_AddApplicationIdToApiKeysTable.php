<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApplicationIdToApiKeysTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('api_keys', [
            'application_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $defaultAppId = $this->resolveDefaultApplicationId();

        if ($defaultAppId !== null) {
            $this->db->table('api_keys')
                ->where('application_id', null)
                ->update(['application_id' => $defaultAppId]);
        }

        $this->forge->addForeignKey('application_id', 'applications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->processIndexes('api_keys');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('api_keys', 'api_keys_application_id_foreign');
        $this->forge->dropColumn('api_keys', 'application_id');
    }

    private function resolveDefaultApplicationId(): ?int
    {
        $row = $this->db->table('applications')
            ->where('code', 'self')
            ->orWhere('name', 'self')
            ->limit(1)
            ->get()?->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }
}
