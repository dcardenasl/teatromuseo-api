<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyHttpDomainClient;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacyHttpDomainClientTest extends TestCase
{
    public function testRetriesOnceAfterA429ThenSucceeds(): void
    {
        // Bulk migration runs hammer the hub's /files/upload endpoint fast enough to trip its
        // per-minute rate limit. The client must back off using the server's retry_after and
        // succeed on retry instead of permanently recording the item as rejected.
        $throttled = $this->createMock(ResponseInterface::class);
        $throttled->method('getStatusCode')->willReturn(429);
        $throttled->method('getBody')->willReturn(json_encode(['retry_after' => 1], JSON_THROW_ON_ERROR));
        $throttled->method('getHeaderLine')->willReturn('');

        $ok = $this->createMock(ResponseInterface::class);
        $ok->method('getStatusCode')->willReturn(200);
        $ok->method('getBody')->willReturn(json_encode(['data' => ['id' => 42]], JSON_THROW_ON_ERROR));

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($throttled, $ok);

        $client = new LegacyHttpDomainClient('http://hub.test', 'token', $http);

        $result = $client->post('/files/upload', ['foo' => 'bar']);

        $this->assertSame(42, $result['data']['id']);
    }
}
