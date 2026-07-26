<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Users;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class UserControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
        $this->actAs('admin');

        // Ensure static context is set for background model operations (Auditable trait)
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
    }

    public function testListUsersRequiresAuth(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/users');

        $result->assertStatus(401);
    }

    public function testListUsersReturnsSuccess(): void
    {
        $result = $this->get('/api/v1/users');

        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
    }

    public function testAdminCanCreateUpdateAndDeleteUser(): void
    {
        $createResult = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'created@example.com',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = $createJson['data']['id'] ?? null;
        $this->assertNotNull($createdId);
        $this->assertEquals('active', $createJson['data']['status'] ?? null);

        $this->resetRequest();

        $updateResult = $this->withBodyFormat('json')->put("/api/v1/users/{$createdId}", [
            'first_name' => 'Updated',
        ]);

        $updateResult->assertStatus(200);

        $deleteResult = $this->delete("/api/v1/users/{$createdId}");

        $deleteResult->assertStatus(200);
    }

    public function testAdminCreateUserIsLoggedInAudit(): void
    {
        $this->enableAudit();

        $createResult = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'audit-created@example.com',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = (int) ($createJson['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdId);

        $auditCount = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $createdId)
            ->where('action', 'create')
            ->countAllResults();

        $this->assertGreaterThan(0, $auditCount);
    }

    // Legacy role-hierarchy tests removed in Sprint 3.5 (RBAC). Authorization is
    // now enforced at the route level via `permission:users.write`; finer-grained
    // restrictions (e.g. "admin cannot promote to superadmin") are now expressed
    // by which permissions are attached to which role in the IAM model.

    public function testSuperadminCanManageAdminUsers(): void
    {
        $this->actAs('superadmin');
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));

        $createResult = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'managed-admin@example.com',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = (int) ($createJson['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdId);

        $this->resetRequest();

        $updateResult = $this->withBodyFormat('json')->put("/api/v1/users/{$createdId}", [
            'first_name' => 'Managed',
        ]);
        $updateResult->assertStatus(200);

        $deleteResult = $this->delete("/api/v1/users/{$createdId}");
        $deleteResult->assertStatus(200);
    }

    public function testNonAdminCannotCreateUser(): void
    {
        $this->actAs('user');

        $result = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'blocked@example.com',
        ]);

        $result->assertStatus(403);
    }

    public function testAdminCreateUserRejectsPasswordField(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'blocked-password@example.com',
            'password' => 'ValidPass123!',
        ]);

        $result->assertStatus(422);

        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('password', $json['errors']);
    }

    public function testAdminCannotApproveInvitedUser(): void
    {
        $createResult = $this->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'already-invited@example.com',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = $createJson['data']['id'] ?? null;
        $this->assertNotNull($createdId);

        $approveResult = $this->post("/api/v1/users/{$createdId}/approve");

        $approveResult->assertStatus(409);
    }

    public function testAdminCanApprovePendingApprovalUser(): void
    {
        $pendingUserId = $this->createUser(
            'pending-approval@example.com',
            'ValidPass123!',
            'user',
            'pending_approval'
        );

        $approveResult = $this->post("/api/v1/users/{$pendingUserId}/approve");
        $approveResult->assertStatus(200);

        $approveJson = $this->getResponseJson($approveResult);
        $this->assertEquals('success', $approveJson['status'] ?? null);
        $this->assertEquals('active', $approveJson['data']['status'] ?? null);
    }

    public function testAdminCannotApproveAlreadyActiveUser(): void
    {
        $activeUserId = $this->createUser(
            'already-active-feature@example.com',
            'ValidPass123!',
            'user',
            'active'
        );

        $approveResult = $this->post("/api/v1/users/{$activeUserId}/approve");
        $approveResult->assertStatus(409);
    }

    public function testAdminCannotChangeAnotherUserEmail(): void
    {
        // Default actAs('admin') in setUp() — admin has users.write but not iam.superadmin-access.
        $targetId = $this->createUser('email-target@example.com', 'ValidPass123!', 'user');
        $this->resetRequest();

        $result = $this->withBodyFormat('json')->put("/api/v1/users/{$targetId}", [
            'email' => 'hijacked@example.com',
        ]);

        $result->assertStatus(403);

        /** @var \App\Entities\UserEntity|null $unchanged */
        $unchanged = $this->userModel->find($targetId);
        $this->assertNotNull($unchanged);
        $this->assertSame('email-target@example.com', strtolower((string) $unchanged->email));
    }

    public function testSuperadminCanChangeAnotherUserEmail(): void
    {
        $this->actAs('superadmin');
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));

        $targetId = $this->createUser('to-rename@example.com', 'ValidPass123!', 'user');
        $this->resetRequest();

        $result = $this->withBodyFormat('json')->put("/api/v1/users/{$targetId}", [
            'email' => 'renamed@example.com',
        ]);

        $result->assertStatus(200);

        /** @var \App\Entities\UserEntity|null $renamed */
        $renamed = $this->userModel->find($targetId);
        $this->assertNotNull($renamed);
        $this->assertSame('renamed@example.com', strtolower((string) $renamed->email));
    }
}
