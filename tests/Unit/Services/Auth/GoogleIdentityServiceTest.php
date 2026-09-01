<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\GoogleIdentityService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;

/**
 * GoogleIdentityService Unit Tests
 *
 * Only the guard-clause branches that run before the actual Google\Client
 * call are exercised here — verifying a real ID token requires either a
 * live network call or a genuine Google-signed JWT, which belongs in a
 * higher-level/manual integration check, not a fast unit test.
 */
class GoogleIdentityServiceTest extends CIUnitTestCase
{
    public function testVerifyIdTokenThrowsWhenTokenIsEmpty(): void
    {
        $service = new GoogleIdentityService('a-client-id');

        $this->expectException(AuthenticationException::class);

        $service->verifyIdToken('');
    }

    public function testVerifyIdTokenThrowsWhenTokenIsOnlyWhitespace(): void
    {
        $service = new GoogleIdentityService('a-client-id');

        $this->expectException(AuthenticationException::class);

        $service->verifyIdToken('   ');
    }

    public function testVerifyIdTokenThrowsWhenClientIdNotConfigured(): void
    {
        $service = new GoogleIdentityService('');

        $this->expectException(ServiceUnavailableException::class);

        $service->verifyIdToken('some-token');
    }
}
