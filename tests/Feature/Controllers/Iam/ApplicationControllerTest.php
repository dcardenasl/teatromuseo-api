<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * HTTP tests for ApplicationController (LAYER-07: previously zero test
 * coverage). Applications are a read-only lookup gated by
 * `iam.superadmin-access` — created via `php spark apps:bootstrap`, not
 * through this API.
 *
 * @internal
 */
final class ApplicationControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testIndexRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();

        $result = $this->get('/api/v1/iam/applications');

        $result->assertStatus(401);
    }

    public function testIndexForbiddenWithoutSuperadminAccess(): void
    {
        $this->actAs('admin');

        $result = $this->get('/api/v1/iam/applications');

        $result->assertStatus(403);
    }

    public function testIndexReturnsApplicationsForSuperadmin(): void
    {
        $this->actAs('superadmin');
        $this->insertApplication('app-ctrl-index', 'App Ctrl Index');

        $result = $this->get('/api/v1/iam/applications');

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status']);
        $names = array_column($json['data'] ?? [], 'name');
        $this->assertContains('App Ctrl Index', $names);
    }

    public function testShowRequiresAuthentication(): void
    {
        $this->clearTestRequestHeaders();

        $result = $this->get('/api/v1/iam/applications/1');

        $result->assertStatus(401);
    }

    public function testShowReturnsApplicationForSuperadmin(): void
    {
        $this->actAs('superadmin');
        $appId = $this->insertApplication('app-ctrl-show', 'App Ctrl Show');

        $result = $this->get("/api/v1/iam/applications/{$appId}");

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame($appId, $json['data']['id']);
        $this->assertSame('App Ctrl Show', $json['data']['name']);
    }

    public function testShowReturns404ForUnknownApplication(): void
    {
        $this->actAs('superadmin');

        $result = $this->get('/api/v1/iam/applications/999999');

        $result->assertStatus(404);
    }

    private function insertApplication(string $code, string $name): int
    {
        $db = \Config\Database::connect();
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }
}
