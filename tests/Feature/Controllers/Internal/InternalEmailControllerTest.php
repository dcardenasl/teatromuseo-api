<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Internal;

use Config\Services;
use Tests\Support\ApiTestCase;

/**
 * HTTP tests for InternalEmailController (LAYER-07: previously zero test
 * coverage). Gated by `appKeyRequired` — trusted Domain apps call this
 * queue() endpoint via X-App-Key, no JWT involved.
 *
 * @internal
 */
final class InternalEmailControllerTest extends ApiTestCase
{
    public function testQueueRequiresAppKey(): void
    {
        $this->clearTestRequestHeaders();

        $result = $this->withBodyFormat('json')->post('/api/v1/internal/email/queue', [
            'to'      => 'someone@example.test',
            'subject' => 'Hello',
            'message' => 'Body',
        ]);

        $result->assertStatus(401);
    }

    public function testQueueRejectsInvalidAppKey(): void
    {
        $result = $this->withHeaders(['X-App-Key' => 'not-a-real-key'])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'someone@example.test',
                'subject' => 'Hello',
                'message' => 'Body',
            ]);

        $result->assertStatus(403);
    }

    public function testQueueValidatesRequiredFields(): void
    {
        $rawKey = $this->seedAppAndKey('email-domain');

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'subject' => 'Missing recipient',
            ]);

        $result->assertStatus(422);
    }

    public function testQueueValidatesEmailFormat(): void
    {
        $rawKey = $this->seedAppAndKey('email-domain-fmt');

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'not-an-email',
                'subject' => 'Subject',
                'message' => 'Body',
            ]);

        $result->assertStatus(422);
    }

    public function testQueueWithValidAppKeyAndPayloadReturnsJobId(): void
    {
        $rawKey = $this->seedAppAndKey('email-domain-ok');

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/internal/email/queue', [
                'to'      => 'recipient@example.test',
                'subject' => 'Internal M2M email',
                'message' => '<p>Body</p>',
            ]);

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('job_id', $json['data']);
        $this->assertIsInt($json['data']['job_id']);
    }

    /**
     * Insert an application + an active API key bound to it (no permissions
     * required — this endpoint is only gated by appKeyRequired, not
     * `permission:*`). Returns the raw API key string.
     */
    private function seedAppAndKey(string $appCode): string
    {
        $db = \Config\Database::connect();

        $db->table('applications')->insert([
            'code'       => $appCode,
            'name'       => ucfirst($appCode),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $appId = (int) $db->insertID();

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
