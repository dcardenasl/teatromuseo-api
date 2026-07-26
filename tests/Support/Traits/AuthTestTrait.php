<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\UserModel;
use CodeIgniter\Test\FeatureTestTrait;

trait AuthTestTrait
{
    use FeatureTestTrait;

    protected ?int $currentUserId = null;
    protected ?string $currentUserRole = null;

    /**
     * Creates a user and returns their identity and token.
     * Uses real login process to ensure framework compatibility.
     *
     * @return array{userId: int, role: string, token: string}
     */
    protected function actAs(string $role = 'user', array $overrides = []): array
    {
        $email = $overrides['email'] ?? 'testuser' . uniqid() . '@example.com';
        $password = 'ValidPass123!';

        $this->currentUserId = $this->createUser(
            $email,
            $password,
            $role,
            $overrides['status'] ?? 'active',
            $overrides['verified'] ?? true
        );
        $this->currentUserRole = $role;

        // Perform real login to get a valid JWT token
        $token = $this->loginAndGetToken($email, $password);

        // Inject identity into static holder for direct service access in tests
        $permissions = \App\Support\TestPermissionResolver::permissionsForRole($role);
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(
            (int) $this->currentUserId,
            [],
            $permissions
        ));

        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Test-User-Id' => (string) $this->currentUserId,
            'X-Test-User-Role' => (string) $role
        ];

        $this->setTestRequestHeaders($headers);

        return [
            'user_id' => $this->currentUserId,
            'role'   => $role,
            'token'  => $token
        ];
    }

    /**
     * Explicitly enables audit logging for the current test.
     */
    protected function enableAudit(): void
    {
        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = true;
    }

    /**
     * Disables audit logging (default in tests).
     */
    protected function disableAudit(): void
    {
        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = false;
    }    protected function createUser(
        string $email,
        string $password,
        string $role = 'user',
        string $status = 'active',
        bool $verified = true
    ): int {
        $userModel = new UserModel();

        // Ensure we don't try to re-create the same user in a test run
        $existing = $userModel->where('email', $email)->first();
        if ($existing) {
            $userId = (int) $existing->id;
            $this->ensureMembership($userId, $role);
            return $userId;
        }

        $userId = (int) $userModel->insert([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => $verified ? date('Y-m-d H:i:s') : null,
        ]);

        $this->ensureMembership($userId, $role);

        return $userId;
    }

    /**
     * Assigns the given global role to the user via the user_roles table so
     * that EffectivePermissionsResolver returns the expected permission set.
     */
    private function ensureMembership(int $userId, string $roleCode): void
    {
        $db = \Config\Database::connect();

        $role = $db->table('roles')->where('code', $roleCode)->get()?->getRowArray();
        if ($role === null) {
            return;
        }

        $userRoleExists = $db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', (int) $role['id'])
            ->countAllResults() > 0;

        if (! $userRoleExists) {
            $db->table('user_roles')->insert([
                'user_id'             => $userId,
                'role_id'             => (int) $role['id'],
                'assigned_at'         => date('Y-m-d H:i:s'),
                'assigned_by_user_id' => null,
            ]);
        }
    }

    protected function loginAndGetToken(string $email, string $password): string
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        return $json['access_token'] ?? ($json['data']['access_token'] ?? '');
    }
}
