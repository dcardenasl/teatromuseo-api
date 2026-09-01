<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tokens\Support;

use App\Services\Tokens\Support\ApiKeyMaterialService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit tests for ApiKeyMaterialService — the raw-key generation and hashing
 * used for `api_keys.key_prefix`/`key_hash`. No dependencies to mock; pure
 * value-object behaviour.
 *
 * @internal
 */
final class ApiKeyMaterialServiceTest extends CIUnitTestCase
{
    private ApiKeyMaterialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiKeyMaterialService();
    }

    public function testGenerateRawKeyHasApkPrefix(): void
    {
        $key = $this->service->generateRawKey();

        $this->assertStringStartsWith('apk_', $key);
    }

    public function testGenerateRawKeyHasExpectedLength(): void
    {
        // 'apk_' (4) + 24 random bytes hex-encoded (48 chars) = 52.
        $key = $this->service->generateRawKey();

        $this->assertSame(52, strlen($key));
    }

    public function testGenerateRawKeyProducesUniqueValues(): void
    {
        $keys = [];
        for ($i = 0; $i < 50; $i++) {
            $keys[] = $this->service->generateRawKey();
        }

        $this->assertCount(50, array_unique($keys), 'Generated keys must not collide');
    }

    public function testGenerateRawKeySuffixIsLowercaseHex(): void
    {
        $key = $this->service->generateRawKey();
        $suffix = substr($key, 4);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $suffix);
    }

    public function testHashIsDeterministic(): void
    {
        $key = $this->service->generateRawKey();

        $this->assertSame($this->service->hash($key), $this->service->hash($key));
    }

    public function testHashIsSha256HexDigest(): void
    {
        $hash = $this->service->hash('apk_deadbeef');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertSame(hash('sha256', 'apk_deadbeef'), $hash);
    }

    public function testDifferentKeysProduceDifferentHashes(): void
    {
        $a = $this->service->hash('apk_aaaaaaaaaaaaaaaa');
        $b = $this->service->hash('apk_bbbbbbbbbbbbbbbb');

        $this->assertNotSame($a, $b);
    }

    public function testHashDoesNotReturnTheRawKey(): void
    {
        $key = $this->service->generateRawKey();

        $this->assertNotSame($key, $this->service->hash($key));
    }
}
