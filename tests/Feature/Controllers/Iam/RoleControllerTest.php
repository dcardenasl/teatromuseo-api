<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * HTTP smoke tests for RoleController. The default route group wraps
 * every endpoint in the jwtauth filter, so an unauthenticated request must
 * return 401 — a sufficient signal that the route was registered and wired.
 *
 * Extend with authenticated 200 flows (via AuthTestTrait) as business rules
 * solidify.
 *
 * @internal
 */
final class RoleControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testIndexRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/iam/roles');

        $result->assertStatus(401);
    }

    public function testWorkspaceReturnsRoleAndPermissionCatalog(): void
    {
        $this->actAs('superadmin');
        $role = \Config\Database::connect()->table('roles')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        $this->assertIsArray($role);

        $result = $this->get('/api/v1/iam/roles/' . (int) $role['id'] . '/workspace');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame((int) $role['id'], (int) ($body['data']['role']['id'] ?? 0));
        $this->assertIsArray($body['data']['allPermissions'] ?? null);
        $this->assertIsArray($body['data']['assignedPermissionIds'] ?? null);
    }
}
