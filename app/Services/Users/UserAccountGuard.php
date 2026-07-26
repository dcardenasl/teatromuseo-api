<?php

declare(strict_types=1);

namespace App\Services\Users;

use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Security\Hasher;

/**
 * Centralized policy checks for user authentication access.
 */
class UserAccountGuard
{
    /**
     * Ensure the user account can authenticate.
     *
     * @param object $user
     * @return void
     */
    public function assertCanAuthenticate(object $user): void
    {
        if (($user->status ?? null) === 'invited') {
            throw new AuthorizationException(
                lang('Auth.accountSetupRequired'),
                ['status' => lang('Auth.accountSetupRequired')]
            );
        }

        if (($user->status ?? null) !== 'active') {
            throw new AuthorizationException(
                lang('Auth.accountPendingApproval'),
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $isGoogleOAuth = ($user->oauth_provider ?? null) === 'google';

        if (
            Hasher::isEmailVerificationRequired()
            && $user->email_verified_at === null
            && ! $isGoogleOAuth
        ) {
            throw new AuthenticationException(
                lang('Auth.emailNotVerified'),
                ['email' => lang('Auth.emailNotVerified')]
            );
        }
    }
}
