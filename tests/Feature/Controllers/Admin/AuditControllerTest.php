<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\AuditLogModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class AuditControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected AuditLogModel $auditLogModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogModel = new AuditLogModel();
        $this->actAs('admin');

        // Ensure static context is set for background model operations (Auditable trait)
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
    }

    public function testAuditRequiresAdmin(): void
    {
        $this->actAs('user');

        $result = $this->get('/api/v1/audit');

        $result->assertStatus(403);
    }

    public function testAuditEndpointsReturnSuccessForAdmin(): void
    {
        $logId = $this->createAuditLog($this->currentUserId);

        $listResult = $this->get('/api/v1/audit');

        $listResult->assertStatus(200);

        $showResult = $this->get("/api/v1/audit/{$logId}");

        $showResult->assertStatus(200);

        $entityResult = $this->get("/api/v1/audit/entity/users/{$this->currentUserId}");

        $entityResult->assertStatus(200);
    }

    public function testAuditByEntityAcceptsSingularAlias(): void
    {
        $this->createAuditLog($this->currentUserId);

        $entityResult = $this->get("/api/v1/audit/entity/user/{$this->currentUserId}");

        $entityResult->assertStatus(200);

        $json = json_decode($entityResult->getJSON(), true);
        $this->assertEquals('success', $json['status'] ?? null);
        $this->assertNotEmpty($json['data'] ?? []);
    }

    private function createAuditLog(int $userId): int
    {
        return (int) $this->auditLogModel->insert([
            'user_id' => $userId,
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => $userId,
            'old_values' => null,
            'new_values' => json_encode(['email' => 'audit@example.com']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'tests',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
