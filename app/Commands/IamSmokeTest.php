<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;

/**
 * Smoke test for the IAM admin endpoints.
 *
 * Mints a superadmin JWT directly (no password), then hits each IAM listing +
 * the Applications lookup, reporting status code and the first row's keys.
 *
 * Run: php spark iam:smoke-test
 */
class IamSmokeTest extends BaseCommand
{
    protected $group       = 'API';
    protected $name        = 'iam:smoke-test';
    protected $description = 'Hits the IAM endpoints with a superadmin JWT and prints status + row shape.';

    public function run(array $params): int
    {
        $token = $this->mintSuperadminToken();
        if ($token === null) {
            return 1;
        }

        $base      = rtrim((string) (env('app.baseURL') ?: 'http://localhost:8080'), '/');
        $endpoints = [
            'applications' => '/api/v1/iam/applications',
            'permissions'  => '/api/v1/iam/permissions?per_page=3',
            'memberships'  => '/api/v1/iam/memberships?per_page=3',
            'roles'        => '/api/v1/iam/roles?per_page=3',
            'users'        => '/api/v1/users?per_page=3',
        ];

        $exit = 0;
        foreach ($endpoints as $label => $path) {
            $url        = $base . $path;
            [$status, $body] = $this->fetch($url, $token);
            $payload    = json_decode((string) $body, true);
            $items      = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $count      = count($items);
            $color      = ($status >= 200 && $status < 300) ? 'green' : 'red';
            CLI::write(sprintf('%-14s %-3d %s items=%d', $label, $status, $url, $count), $color);

            if ($status >= 400) {
                CLI::write('  body: ' . substr((string) $body, 0, 400), 'red');
                $exit = 1;
                continue;
            }

            if ($count > 0 && isset($items[0]) && is_array($items[0])) {
                CLI::write('  keys: ' . implode(', ', array_keys($items[0])), 'cyan');
            }
        }

        return $exit;
    }

    private function mintSuperadminToken(): ?string
    {
        $db     = Database::connect();
        $result = $db->table('user_roles ur')
            ->select('ur.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('r.code', 'superadmin')
            ->limit(1)
            ->get();

        $row = $result === false ? null : $result->getRowArray();

        if ($row === null) {
            CLI::error('No superadmin found. Run "php spark users:bootstrap-superadmin" first.');

            return null;
        }

        $userId      = (int) $row['user_id'];
        $permissions = Services::effectivePermissionsResolver()->resolveAll($userId);
        $tokenVersion = Services::tokenVersionService()->current($userId);

        return Services::jwtService()->encode($userId, $permissions, $tokenVersion);
    }

    /** @return array{int, string|false} */
    private function fetch(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $body   = is_string($result) ? $result : false;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, $body];
    }
}
