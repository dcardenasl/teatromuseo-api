<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * API Smoke Test & Contract Inspector
 *
 * This command simulates requests to key endpoints and prints their JSON response
 * to verify that the snake_case contract is being respected.
 */
class ApiSmokeTest extends BaseCommand
{
    protected $group       = 'API';
    protected $name        = 'api:smoke-test';
    protected $description = 'Hits API endpoints and displays JSON responses to verify contracts.';

    public function run(array $params): void
    {
        CLI::write('🚀 Starting API Smoke Test...', 'cyan');

        // 1. Setup environment
        helper('test');

        // 2. Obtain Token (Using a specialized logic to avoid PHPUnit dependency in Command)
        CLI::write('🔑 Authenticating as SuperAdmin...', 'yellow');
        $token = $this->getSuperAdminToken();

        if (!$token) {
            CLI::error('❌ Failed to obtain authentication token.');
            return;
        }
        CLI::write('✅ Token obtained successfully.', 'green');

        // 3. Define Endpoints to Inspect (Main ones)
        $endpoints = [
            ['GET', 'api/v1/auth/me', 'Current Profile'],
            ['GET', 'api/v1/users', 'User Listing'],
            ['GET', 'api/v1/files', 'File Listing'],
            ['GET', 'api/v1/api-keys', 'API Key Listing'],
            ['GET', 'api/v1/audit', 'Audit Logs'],
            ['GET', 'api/v1/metrics', 'System Metrics'],
        ];

        foreach ($endpoints as [$method, $path, $label]) {
            $this->inspectEndpoint($method, $path, $label, $token);
        }

        CLI::write('🏁 Smoke test completed.', 'cyan');
    }

    private function getSuperAdminToken(): ?string
    {
        $db     = \Config\Database::connect();
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

        $userId = (int) $row['user_id'];
        $jwtService = Services::jwtService();
        $permissions = Services::effectivePermissionsResolver()->resolveAll($userId);
        return $jwtService->encode($userId, $permissions);
    }

    private function inspectEndpoint(string $method, string $path, string $label, string $token): void
    {
        CLI::write("
--- 🔍 Inspecting: $label [$method /$path] ---", 'yellow');

        // We use the internal request simulator
        $request = Services::request();
        $request->setHeader('Authorization', "Bearer $token");
        $request->setHeader('Accept', 'application/json');

        // Simulate the request via CI4 internal runner
        // Note: For a command, we hit the URI directly
        try {
            $response = Services::curlrequest()->request($method, base_url($path), [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                CLI::write("Status: $statusCode OK", 'green');
                $this->printContractKeys($body);
            } else {
                CLI::write("Status: $statusCode Error", 'red');
                print_r($body);
            }
        } catch (\Exception $e) {
            CLI::error("Error connecting to server: " . $e->getMessage());
            CLI::write("Make sure your server is running: php spark serve", 'yellow');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function printContractKeys(array $payload): void
    {
        $data = $payload['data'] ?? $payload;

        // If it's a list, take the first element to show keys
        if (isset($data[0]) && is_array($data[0])) {
            CLI::write("Response is a List. Sample item keys:", 'cyan');
            $keys = array_keys($data[0]);
        } else {
            CLI::write("Response Data keys:", 'cyan');
            $keys = array_keys((array)$data);
        }

        foreach ($keys as $key) {
            $color = preg_match('/[A-Z]/', $key) ? 'red' : 'green';
            CLI::write("  • $key", $color);
        }

        // Show a snippet
        CLI::write("Snippet:", 'yellow');
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $json = $json === false ? '(unable to encode payload)' : $json;
        CLI::write(strlen($json) > 500 ? substr($json, 0, 500) . "...
(truncated)" : $json);
    }
}
