<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes the legacy IAM membership tables. App-scoped permissions are now
 * derived directly from user_roles by EffectivePermissionsResolver.
 */
class DropMembershipsTables extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('membership_roles')) {
            $this->forge->dropTable('membership_roles', true);
        }

        if ($this->db->tableExists('app_user_memberships')) {
            $this->forge->dropTable('app_user_memberships', true);
        }
    }

    public function down(): void
    {
        // Forward-only. Use the original CreateAppUserMembershipsTable and
        // CreateMembershipRolesTable migrations to recreate the legacy schema.
    }
}
