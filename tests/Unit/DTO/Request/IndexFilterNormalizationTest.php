<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Request;

use App\DTO\Request\ApiKeys\ApiKeyIndexRequestDTO;
use App\DTO\Request\Audit\AuditIndexRequestDTO;
use App\DTO\Request\Users\UserIndexRequestDTO;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class IndexFilterNormalizationTest extends CIUnitTestCase
{
    public function testAuditIndexReadsActionFromFilterArray(): void
    {
        $dto = new AuditIndexRequestDTO([
            'filter' => [
                'action' => 'login_success',
            ],
        ], service('validation'));

        $data = $dto->toArray();

        $this->assertSame('login_success', $data['filter']['action']['eq']);
    }

    public function testAuditIndexReadsResultSeverityAndRequestIdFromFilterArray(): void
    {
        $dto = new AuditIndexRequestDTO([
            'filter' => [
                'result' => 'denied',
                'severity' => 'critical',
                'request_id' => 'req_abc123',
            ],
        ], service('validation'));

        $data = $dto->toArray();

        $this->assertSame('denied', $data['filter']['result']['eq']);
        $this->assertSame('critical', $data['filter']['severity']['eq']);
        $this->assertSame('req_abc123', $data['filter']['request_id']['eq']);
    }

    public function testUserIndexReadsStatusFromFilterArray(): void
    {
        $dto = new UserIndexRequestDTO([
            'filter' => [
                'status' => 'active',
            ],
        ], service('validation'));

        $data = $dto->toArray();

        $this->assertSame('active', $data['filter']['status']['eq']);
        $this->assertArrayNotHasKey('role', $data['filter'] ?? []);
    }

    public function testApiKeyIndexReadsIsActiveAndNameFromFilterArray(): void
    {
        $dto = new ApiKeyIndexRequestDTO([
            'filter' => [
                'is_active' => '1',
                'name' => 'integration',
            ],
        ], service('validation'));

        $data = $dto->toArray();

        $this->assertSame(1, $data['filter']['is_active']['eq']);
        $this->assertSame('integration', $data['search']);
    }
}
