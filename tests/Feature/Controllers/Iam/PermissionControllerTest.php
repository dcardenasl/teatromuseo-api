<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Iam;

use Config\Services;
use Tests\Support\ApiTestCase;

/**
 * HTTP smoke tests for PermissionController. The default route group wraps
 * every endpoint in the jwtauth filter, so an unauthenticated request must
 * return 401 — a sufficient signal that the route was registered and wired.
 *
 * Also exercises JwtAuthFilter + PermissionFilter against M2M (service)
 * tokens, which carry a `sub: service:<code>` claim and no `uid`. The pair
 * must not crash on the missing uid (API-005) and must surface 403 (not
 * 500) when the service scope lacks the route's required permission.
 *
 * Extend with authenticated 200 flows (via AuthTestTrait) as business rules
 * solidify.
 *
 * @internal
 */
final class PermissionControllerTest extends ApiTestCase
{
    public function testIndexRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/iam/permissions');

        $result->assertStatus(401);
    }

    public function testIndexWithServiceTokenLackingIamAccessReturns403(): void
    {
        // Bug API-005 reproducer: before the fix, a service token reaching a
        // jwtauth-protected route crashed in JwtAuthFilter (Undefined property
        // stdClass::$uid) → HTTP 500. Expected behaviour: filter accepts the
        // token, PermissionFilter compares scope to the required code, and the
        // missing `iam.admin-access` produces a 403.
        $rawKey = $this->seedAppAndKey('mydomain', ['mydomain.read']);

        $tokenResult = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $tokenResult->assertStatus(200);
        $token = (string) $this->getResponseJson($tokenResult)['data']['access_token'];

        $this->resetRequest();
        $this->clearTestRequestHeaders();

        $result = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->get('/api/v1/iam/permissions');

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    /**
     * Insert an application + permissions + an active API key bound to it.
     * Returns the raw API key string.
     *
     * @param list<string> $permissionCodes
     */
    private function seedAppAndKey(string $appCode, array $permissionCodes): string
    {
        $db = \Config\Database::connect();

        $db->table('applications')->insert([
            'code'       => $appCode,
            'name'       => ucfirst($appCode),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $appId = (int) $db->insertID();

        foreach ($permissionCodes as $code) {
            [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

            $db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => $resource,
                'action'         => $action,
                'description'    => "Test permission {$code}",
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        $db->table('api_keys')->insert([
            'application_id'      => $appId,
            'name'                => "{$appCode}-key",
            'key_prefix'          => substr($rawKey, 0, 8),
            'key_hash'            => $material->hash($rawKey),
            'is_active'           => 1,
            'rate_limit_requests' => 600,
            'rate_limit_window'   => 60,
            'user_rate_limit'     => 60,
            'ip_rate_limit'       => 200,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        return $rawKey;
    }
}
