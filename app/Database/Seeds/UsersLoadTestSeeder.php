<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsersLoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $count = max(1, (int) env('USERS_FAKE_COUNT', 1000));
        $batchSize = max(50, (int) env('USERS_FAKE_BATCH_SIZE', 250));
        $resetBeforeSeed = filter_var(env('USERS_FAKE_RESET', true), FILTER_VALIDATE_BOOLEAN);
        $emailDomain = (string) env('USERS_FAKE_EMAIL_DOMAIN', 'example.test');
        $emailPrefix = (string) env('USERS_FAKE_EMAIL_PREFIX', 'loadtest.user');
        $defaultPassword = (string) env('USERS_FAKE_PASSWORD', 'Passw0rd!123');

        $now = date('Y-m-d H:i:s');
        $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $statuses = ['active', 'pending_approval'];

        if ($resetBeforeSeed) {
            $this->db->table('users')
                ->like('email', $emailPrefix . '%', 'after')
                ->delete();
        }

        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $status = $statuses[$i % count($statuses)];
            $isActive = $status === 'active';

            $rows[] = [
                'email' => sprintf('%s%04d@%s', $emailPrefix, $i, $emailDomain),
                'first_name' => 'Load' . $i,
                'last_name' => 'User' . $i,
                'password' => $passwordHash,
                'status' => $status,
                'approved_at' => $isActive ? $now : null,
                'approved_by' => null,
                'invited_at' => $now,
                'invited_by' => null,
                'email_verified_at' => $isActive ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= $batchSize) {
                $this->db->table('users')->insertBatch($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->db->table('users')->insertBatch($rows);
        }
    }
}
