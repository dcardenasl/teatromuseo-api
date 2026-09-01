<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Domains;

use App\Libraries\Domains\DomainFileUsageClient;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\DomainWebhooks;

/**
 * DomainFileUsageClient Unit Tests
 */
class DomainFileUsageClientTest extends CIUnitTestCase
{
    private function configWithDomains(): DomainWebhooks
    {
        $config = new DomainWebhooks();
        $config->internalSecret = 'test-secret';
        $config->domains = ['cms' => 'http://cms.local'];
        $config->httpTimeoutSeconds = 3;

        return $config;
    }

    private function emptyConfig(): DomainWebhooks
    {
        $config = new DomainWebhooks();
        $config->internalSecret = '';
        $config->domains = [];

        return $config;
    }

    private function mockResponse(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn(json_encode($body) ?: '{}');

        return $response;
    }

    // ==================== collectUsageSnapshot() TESTS ====================

    public function testCollectUsageSnapshotReturnsEmptyWhenSecretIsMissing(): void
    {
        $client = new DomainFileUsageClient($this->emptyConfig());

        $result = $client->collectUsageSnapshot(1);

        $this->assertFalse($result['complete']);
        $this->assertSame([], $result['sources']);
        $this->assertSame([], $result['usages']);
    }

    public function testCollectUsageSnapshotAggregatesUsagesFromDomain(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willReturn($this->mockResponse(200, [
            'data' => [
                'usages' => [
                    ['source' => 'cms', 'resource' => 'pages', 'resource_id' => 10, 'label' => 'Home', 'role' => 'cover'],
                ],
            ],
        ]));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $result = $client->collectUsageSnapshot(1);

        $this->assertTrue($result['complete']);
        $this->assertSame(['cms' => 'ok'], $result['sources']);
        $this->assertCount(1, $result['usages']);
        $this->assertSame('pages', $result['usages'][0]['resource']);
        $this->assertSame(10, $result['usages'][0]['resource_id']);
    }

    public function testCollectUsageSnapshotMarksDomainUnavailableOnHttpError(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willReturn($this->mockResponse(500, []));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $result = $client->collectUsageSnapshot(1);

        $this->assertFalse($result['complete']);
        $this->assertSame(['cms' => 'unavailable'], $result['sources']);
        $this->assertSame([], $result['usages']);
    }

    public function testCollectUsageSnapshotMarksDomainUnavailableOnTransportException(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willThrowException(new \RuntimeException('connection refused'));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $result = $client->collectUsageSnapshot(1);

        $this->assertFalse($result['complete']);
        $this->assertSame(['cms' => 'unavailable'], $result['sources']);
    }

    public function testCollectUsageSnapshotIncludesContextWhenPresent(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willReturn($this->mockResponse(200, [
            'data' => [
                'usages' => [
                    ['source' => 'cms', 'resource' => 'pages', 'resource_id' => 10, 'context' => ['slug' => 'home']],
                ],
            ],
        ]));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $result = $client->collectUsageSnapshot(1);

        $this->assertSame(['slug' => 'home'], $result['usages'][0]['context']);
    }

    // ==================== collectUsages() TESTS ====================

    public function testCollectUsagesReturnsFlattenedList(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willReturn($this->mockResponse(200, [
            'data' => [
                'usages' => [
                    ['source' => 'cms', 'resource' => 'pages', 'resource_id' => 10, 'label' => null, 'role' => 'default'],
                ],
            ],
        ]));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $usages = $client->collectUsages(1);

        $this->assertCount(1, $usages);
        $this->assertSame('cms', $usages[0]['source']);
        $this->assertSame('default', $usages[0]['role']);
        $this->assertNull($usages[0]['label']);
    }

    // ==================== broadcastInvalidate() TESTS ====================

    public function testBroadcastInvalidateIsNoopWhenSecretIsMissing(): void
    {
        $client = new DomainFileUsageClient($this->emptyConfig());

        // No exception, no HTTP client required.
        $client->broadcastInvalidate(1);
        $this->addToAssertionCount(1);
    }

    public function testBroadcastInvalidateCallsEachConfiguredDomain(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp
            ->expects($this->once())
            ->method('request')
            ->with('POST', $this->stringContains('/api/v1/internal/files/1/invalidate-cache'), $this->anything())
            ->willReturn($this->mockResponse(200, []));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $client->broadcastInvalidate(1);
    }

    public function testBroadcastInvalidateSwallowsTransportExceptions(): void
    {
        $mockHttp = $this->createMock(CURLRequest::class);
        $mockHttp->method('request')->willThrowException(new \RuntimeException('down'));

        $client = new DomainFileUsageClient($this->configWithDomains(), $mockHttp);

        $client->broadcastInvalidate(1);
        $this->addToAssertionCount(1);
    }
}
