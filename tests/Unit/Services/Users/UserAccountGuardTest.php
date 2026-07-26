<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users;

use App\Services\Users\UserAccountGuard;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use stdClass;

/**
 * UserAccountGuard is the single source of truth for "can this user
 * authenticate now?" — JwtAuthFilter and AuthService both delegate to it.
 * These tests lock in the contract so future changes can't silently regress
 * email verification or status enforcement.
 */
class UserAccountGuardTest extends CIUnitTestCase
{
    private UserAccountGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new UserAccountGuard();
    }

    private function user(array $overrides = []): stdClass
    {
        // Use array_key_exists, NOT ??, because callers legitimately pass null
        // for fields like email_verified_at to simulate an unverified user.
        $defaults = [
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'oauth_provider'    => null,
        ];
        $merged = $overrides + $defaults;

        $user                    = new stdClass();
        $user->status            = $merged['status'];
        $user->email_verified_at = $merged['email_verified_at'];
        $user->oauth_provider    = $merged['oauth_provider'];

        return $user;
    }

    private function withRequireEmailVerification(bool $required, callable $fn): void
    {
        // Mutating config('Api') alone is not enough — Config\Api re-reads from
        // env in its constructor and other code paths can rebuild a fresh
        // instance. Setting the underlying env var is the reliable knob.
        $previousEnv = getenv('AUTH_REQUIRE_EMAIL_VERIFICATION');
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=' . ($required ? 'true' : 'false'));
        $api                            = config('Api');
        $previousProp                   = $api->requireEmailVerification;
        $api->requireEmailVerification  = $required;

        try {
            $fn();
        } finally {
            $api->requireEmailVerification = $previousProp;
            if ($previousEnv === false) {
                putenv('AUTH_REQUIRE_EMAIL_VERIFICATION');
            } else {
                putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=' . $previousEnv);
            }
        }
    }

    public function testActiveVerifiedUserPasses(): void
    {
        $this->withRequireEmailVerification(true, function (): void {
            $this->guard->assertCanAuthenticate($this->user());
            $this->addToAssertionCount(1); // no exception
        });
    }

    public function testInvitedStatusThrowsAuthorizationException(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->guard->assertCanAuthenticate($this->user(['status' => 'invited']));
    }

    public function testPendingApprovalStatusThrowsAuthorizationException(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->guard->assertCanAuthenticate($this->user(['status' => 'pending_approval']));
    }

    public function testSuspendedStatusThrowsAuthorizationException(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->guard->assertCanAuthenticate($this->user(['status' => 'suspended']));
    }

    public function testUnverifiedEmailThrowsWhenVerificationRequired(): void
    {
        $this->withRequireEmailVerification(true, function (): void {
            $this->expectException(AuthenticationException::class);
            $this->guard->assertCanAuthenticate($this->user(['email_verified_at' => null]));
        });
    }

    public function testUnverifiedEmailPassesWhenVerificationDisabled(): void
    {
        $this->withRequireEmailVerification(false, function (): void {
            $this->guard->assertCanAuthenticate($this->user(['email_verified_at' => null]));
            $this->addToAssertionCount(1);
        });
    }

    public function testGoogleOAuthUserPassesEvenWhenUnverified(): void
    {
        $this->withRequireEmailVerification(true, function (): void {
            $this->guard->assertCanAuthenticate($this->user([
                'email_verified_at' => null,
                'oauth_provider'    => 'google',
            ]));
            $this->addToAssertionCount(1);
        });
    }

    public function testStatusTakesPrecedenceOverEmailVerification(): void
    {
        // A pending user with unverified email gets the status error first,
        // not the email verification error. Ensures the order is stable.
        $this->withRequireEmailVerification(true, function (): void {
            try {
                $this->guard->assertCanAuthenticate($this->user([
                    'status'            => 'pending_approval',
                    'email_verified_at' => null,
                ]));
                $this->fail('Expected an exception to be thrown.');
            } catch (AuthorizationException $e) {
                $this->addToAssertionCount(1);
            } catch (AuthenticationException $e) {
                $this->fail('Status check must run before email verification check.');
            }
        });
    }
}
