<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * S3Driver Tests
 *
 * Note: These tests verify the driver can be instantiated with proper configuration.
 * Full integration tests would require actual AWS credentials and are better suited
 * for manual testing or dedicated integration test suites.
 */
class S3DriverTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set required environment variables for S3
        putenv('AWS_BUCKET=test-bucket');
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up environment
        putenv('AWS_BUCKET');
        putenv('AWS_DEFAULT_REGION');
        putenv('AWS_ACCESS_KEY_ID');
        putenv('AWS_SECRET_ACCESS_KEY');
        putenv('AWS_URL');
    }

    public function testConstructorThrowsExceptionWhenBucketMissing(): void
    {
        putenv('AWS_BUCKET=');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AWS_BUCKET environment variable is required');

        new \App\Libraries\Storage\Drivers\S3Driver();
    }

    public function testConstructorSucceedsWithRequiredConfig(): void
    {
        $driver = new \App\Libraries\Storage\Drivers\S3Driver();

        $this->assertInstanceOf(\App\Libraries\Storage\Drivers\S3Driver::class, $driver);
    }

    public function testUrlReturnsDefaultS3UrlFormat(): void
    {
        $driver = new \App\Libraries\Storage\Drivers\S3Driver();

        $url = $driver->url('path/to/file.txt');

        $this->assertEquals(
            'https://test-bucket.s3.us-east-1.amazonaws.com/path/to/file.txt',
            $url
        );
    }

    public function testUrlReturnsCustomUrlWhenConfigured(): void
    {
        putenv('AWS_URL=https://cdn.example.com');

        $driver = new \App\Libraries\Storage\Drivers\S3Driver();
        $url = $driver->url('path/to/file.txt');

        $this->assertEquals('https://cdn.example.com/path/to/file.txt', $url);
    }

    public function testUrlTrimsSlashesCorrectly(): void
    {
        putenv('AWS_URL=https://cdn.example.com/');

        $driver = new \App\Libraries\Storage\Drivers\S3Driver();
        $url = $driver->url('/path/to/file.txt');

        $this->assertEquals('https://cdn.example.com/path/to/file.txt', $url);
    }
}
