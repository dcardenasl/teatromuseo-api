<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tokens\JwtService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * JwtService Unit Tests
 *
 * Tests JWT encoding/decoding without external dependencies.
 */
class JwtServiceTest extends CIUnitTestCase
{
    protected JwtService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new JwtService(
            'test-secret-key-for-unit-tests-minimum-32-chars',
            3600,
            'http://localhost:8080'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==================== ENCODE TESTS ====================

    public function testEncodeReturnsValidJwtString(): void
    {
        $token = $this->service->encode(1);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT has 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testEncodeIncludesUserIdInPayload(): void
    {
        $token = $this->service->encode(42);

        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertEquals(42, $decoded->uid);
    }

    public function testEncodeIncludesScopeInPayload(): void
    {
        $token = $this->service->encode(1, ['users.read', 'users.write']);

        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertEqualsCanonicalizing(['users.read', 'users.write'], $decoded->scope);
    }

    public function testEncodeIncludesJtiForRevocation(): void
    {
        $token = $this->service->encode(1);

        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertObjectHasProperty('jti', $decoded);
        $this->assertNotEmpty($decoded->jti);
    }

    public function testEncodeGeneratesUniqueJtiPerToken(): void
    {
        $token1 = $this->service->encode(1);
        $token2 = $this->service->encode(1);

        $decoded1 = $this->service->decode($token1);
        $decoded2 = $this->service->decode($token2);

        $this->assertNotEquals($decoded1->jti, $decoded2->jti);
    }

    public function testEncodeIncludesExpirationClaim(): void
    {
        $token = $this->service->encode(1);

        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertGreaterThan(time(), $decoded->exp);
    }

    // ==================== DECODE TESTS ====================

    public function testDecodeValidTokenReturnsPayload(): void
    {
        $token = $this->service->encode(1);

        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertIsObject($decoded);
    }

    public function testDecodeInvalidTokenReturnsNull(): void
    {
        $result = $this->service->decode('invalid.token.here');

        $this->assertNull($result);
    }

    public function testDecodeEmptyStringReturnsNull(): void
    {
        $result = $this->service->decode('');

        $this->assertNull($result);
    }

    public function testDecodeTamperedTokenReturnsNull(): void
    {
        $token = $this->service->encode(1);

        // Tamper with the payload (middle part)
        $parts = explode('.', $token);
        $parts[1] = 'tampered' . $parts[1];
        $tamperedToken = implode('.', $parts);

        $result = $this->service->decode($tamperedToken);

        $this->assertNull($result);
    }

    // ==================== VALIDATE TESTS ====================

    public function testValidateReturnsTrueForValidToken(): void
    {
        $token = $this->service->encode(1);

        $this->assertTrue($this->service->validate($token));
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse($this->service->validate('not.valid.token'));
    }

    // ==================== HELPER METHODS TESTS ====================

    public function testGetUserIdReturnsCorrectId(): void
    {
        $token = $this->service->encode(123);

        $userId = $this->service->getUserId($token);

        $this->assertEquals(123, $userId);
    }

    public function testGetUserIdReturnsNullForInvalidToken(): void
    {
        $userId = $this->service->getUserId('invalid.token');

        $this->assertNull($userId);
    }

    public function testGetPermissionsReturnsScopeArray(): void
    {
        $token = $this->service->encode(1, ['files.read']);

        $permissions = $this->service->getPermissions($token);

        $this->assertSame(['files.read'], $permissions);
    }

    public function testGetPermissionsReturnsEmptyForInvalidToken(): void
    {
        $permissions = $this->service->getPermissions('invalid.token');

        $this->assertSame([], $permissions);
    }
}
