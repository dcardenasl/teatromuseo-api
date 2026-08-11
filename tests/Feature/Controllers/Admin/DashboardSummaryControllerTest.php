<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * @internal
 */
final class DashboardSummaryControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAs('admin');
    }

    public function testAuthenticatedAdminReceivesStableSummaryEnvelope(): void
    {
        $result = $this->get('/api/v1/admin/dashboard/summary');

        $result->assertStatus(200);
        $body = json_decode((string) $result->getJSON(), true);
        $this->assertIsArray($body);
        $this->assertIsArray($body['data'] ?? null);
        $this->assertSame(1, $body['data']['version']);
        $this->assertArrayHasKey('generated_at', $body['data']);
        $this->assertArrayHasKey('sections', $body['data']);
    }

    public function testRegularUserDoesNotReceiveHubSectionsWithoutTheirPermissions(): void
    {
        $this->actAs('user');

        $result = $this->get('/api/v1/admin/dashboard/summary');

        $result->assertStatus(200);
        $body = json_decode((string) $result->getJSON(), true);
        $sections = $body['data']['sections'] ?? [];
        $this->assertIsArray($sections);
        $this->assertArrayNotHasKey('users', $sections);
        $this->assertArrayNotHasKey('metrics', $sections);
    }
}
