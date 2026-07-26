<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use Config\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * ServiceTokenController Feature Tests
 *
 * Covers POST /api/v1/auth/service-token: success path (decodes JWT and
 * verifies sub/scope/exp), error paths (key without application_id, key
 * inactive, missing or invalid X-App-Key), and downstream revocability.
 */
class ServiceTokenControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testServiceTokenWithValidAppKeyReturns200WithJwt(): void
    {
        [, $rawKey] = $this->createApplicationWithKey('mydomain', [
            'mydomain.access',
            'mydomain.read',
        ]);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertSame('success', $json['status']);
        $data = $json['data'];

        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(900, $data['expires_in']);
        $this->assertEqualsCanonicalizing(
            ['mydomain.access', 'mydomain.read'],
            $data['scope']
        );
        $this->assertNotEmpty($data['access_token']);

        $decoded = $this->decodeJwt((string) $data['access_token']);
        $this->assertSame('service:mydomain', $decoded->sub);
        $this->assertEqualsCanonicalizing(
            ['mydomain.access', 'mydomain.read'],
            (array) $decoded->scope
        );
        $this->assertObjectNotHasProperty('uid', $decoded);
        $this->assertSame(900, $decoded->exp - $decoded->iat);
        $this->assertNotEmpty($decoded->jti);
    }

    public function testServiceTokenWithKeyWithoutApplicationReturns403(): void
    {
        $rawKey = $this->createApiKey('orphan-key', applicationId: null, isActive: true);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testServiceTokenWithInactiveKeyReturns403(): void
    {
        [$appId] = $this->createApplicationWithKey('inactive-app', ['inactive-app.access']);
        $rawKey  = $this->createApiKey('inactive-key', applicationId: $appId, isActive: false);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(403);
    }

    public function testServiceTokenWithoutAppKeyHeaderReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(401);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testServiceTokenWithInvalidAppKeyReturns403(): void
    {
        $result = $this->withHeaders(['X-App-Key' => 'apk_doesnotexist0000000000000000000'])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(403);
    }

    public function testIssuedServiceTokenIsRevocable(): void
    {
        [, $rawKey] = $this->createApplicationWithKey('revocable-app', ['revocable-app.access']);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/service-token');

        $result->assertStatus(200);
        $token = $this->getResponseJson($result)['data']['access_token'];

        $decoded = $this->decodeJwt((string) $token);
        $this->assertNotEmpty($decoded->jti, 'Service tokens must carry a jti for revocation support');

        // Smoke check: revocation service accepts the jti without erroring.
        Services::tokenRevocationService()->revokeToken(
            (string) $decoded->jti,
            (int) $decoded->exp
        );
        $this->assertTrue(
            Services::tokenRevocationService()->isRevoked((string) $decoded->jti),
            'jti should be marked revoked after revokeToken()'
        );
    }

    /**
     * Insert an application + matching permissions + an active API key bound
     * to the application. Returns [applicationId, rawKey].
     *
     * @param list<string> $permissionCodes
     * @return array{0:int,1:string}
     */
    private function createApplicationWithKey(string $appCode, array $permissionCodes): array
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

        $rawKey = $this->createApiKey("{$appCode}-key", applicationId: $appId, isActive: true);

        return [$appId, $rawKey];
    }

    private function createApiKey(string $name, ?int $applicationId, bool $isActive): string
    {
        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        \Config\Database::connect()
            ->table('api_keys')
            ->insert([
                'application_id'      => $applicationId,
                'name'                => $name,
                'key_prefix'          => substr($rawKey, 0, 8),
                'key_hash'            => $material->hash($rawKey),
                'is_active'           => $isActive ? 1 : 0,
                'rate_limit_requests' => 600,
                'rate_limit_window'   => 60,
                'user_rate_limit'     => 60,
                'ip_rate_limit'       => 200,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

        return $rawKey;
    }

    private function decodeJwt(string $token): object
    {
        $secret = (string) config('Api')->jwtSecretKey;

        return JWT::decode($token, new Key($secret, 'HS256'));
    }
}
