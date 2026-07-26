<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\System;

use Tests\Support\ApiTestCase;

class HealthControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('MONITORING_ENABLED=true');
    }

    protected function tearDown(): void
    {
        putenv('MONITORING_ENABLED');
        parent::tearDown();
    }

    public function testReadyEndpointResponds(): void
    {
        $result = $this->get('/ready');

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 503], true));
    }

    public function testLiveEndpointReturnsAlive(): void
    {
        $result = $this->get('/live');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('alive', $json['status']);
    }

    public function testHealthEndpointReturns503WhenMonitoringDisabled(): void
    {
        putenv('MONITORING_ENABLED=false');

        $result = $this->get('/health');
        $result->assertStatus(503);
    }
}
