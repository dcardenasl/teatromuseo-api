<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Iam;

use Tests\Support\ApiTestCase;

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
    public function testIndexRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/iam/roles');

        $result->assertStatus(401);
    }
}
