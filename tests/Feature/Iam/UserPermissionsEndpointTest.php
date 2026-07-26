<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * Feature tests for GET /api/v1/iam/users/{id}/permissions?app=<code>.
 *
 * Covers the contract:
 *  - 401 without authentication.
 *  - 403 when the caller lacks iam.superadmin-access.
 *  - 422 when ?app= is missing.
 *  - 404 when the user does not exist.
 *  - 404 when the application code does not exist.
 *  - 200 with the user_id, application summary, and effective permission codes.
 *
 * @internal
 */
final class UserPermissionsEndpointTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();

        $result = $this->get('/api/v1/iam/users/1/permissions?app=self');

        $result->assertStatus(401);
    }

    public function testAdminWithoutSuperadminAccessIsForbidden(): void
    {
        $this->actAs('admin');
        $targetId = $this->createUser('target-' . uniqid() . '@example.com', 'ValidPass123!', 'user');

        $result = $this->get("/api/v1/iam/users/{$targetId}/permissions?app=self");

        $result->assertStatus(403);
    }

    public function testMissingAppQueryReturns422(): void
    {
        $this->actAs('superadmin');
        $targetId = $this->createUser('missingapp-' . uniqid() . '@example.com', 'ValidPass123!', 'user');

        $result = $this->get("/api/v1/iam/users/{$targetId}/permissions");

        $result->assertStatus(422);
    }

    public function testUnknownUserReturns404(): void
    {
        $this->actAs('superadmin');

        $result = $this->get('/api/v1/iam/users/99999999/permissions?app=self');

        $result->assertStatus(404);
    }

    public function testUnknownAppCodeReturns404(): void
    {
        $this->actAs('superadmin');
        $targetId = $this->createUser('unknown-app-' . uniqid() . '@example.com', 'ValidPass123!', 'user');

        $result = $this->get("/api/v1/iam/users/{$targetId}/permissions?app=does-not-exist");

        $result->assertStatus(404);
    }

    public function testReturnsEffectivePermissionsForUserScopedByApp(): void
    {
        $this->actAs('superadmin');
        $targetId = $this->createUser('subject-' . uniqid() . '@example.com', 'ValidPass123!', 'admin');

        // Invalidate the resolver cache so the response reflects the seeded role mapping.
        \Config\Services::effectivePermissionsResolver()->invalidateForUser($targetId, 1);

        $result = $this->get("/api/v1/iam/users/{$targetId}/permissions?app=self");

        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status']);

        $data = $json['data'];
        $this->assertSame($targetId, $data['user_id']);
        $this->assertSame('self', $data['application']['code']);
        $this->assertSame(1, $data['application']['id']);
        $this->assertIsArray($data['permissions']);
        $this->assertNotEmpty($data['permissions']);
        $this->assertContains('users.write', $data['permissions']);
        $this->assertContains('audit.read', $data['permissions']);
    }
}
