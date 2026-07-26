<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory;

final class RequestAuditContextFactoryTest extends CIUnitTestCase
{
    public function testBuildMetadataUsesProvidedRequestId(): void
    {
        $factory = new RequestAuditContextFactory();
        $request = $this->createMock(RequestInterface::class);

        $request->method('getHeaderLine')
            ->willReturnCallback(static fn (string $header): string => match (strtolower($header)) {
                'x-request-id' => 'req-test-001',
                'user-agent' => 'PHPUnit-UA',
                default => '',
            });
        $request->method('getIPAddress')->willReturn('10.0.0.1');

        $metadata = $factory->buildMetadata($request);

        $this->assertSame('req-test-001', $metadata['request_id']);
        $this->assertSame('10.0.0.1', $metadata['ip_address']);
        $this->assertSame('PHPUnit-UA', $metadata['user_agent']);
    }

    public function testBuildMetadataGeneratesRequestIdWhenMissing(): void
    {
        $factory = new RequestAuditContextFactory();
        $request = $this->createMock(RequestInterface::class);

        $request->method('getHeaderLine')->willReturn('');
        $request->method('getIPAddress')->willReturn('127.0.0.1');

        $metadata = $factory->buildMetadata($request);

        $this->assertIsString($metadata['request_id']);
        $this->assertNotSame('', trim($metadata['request_id']));
    }
}
