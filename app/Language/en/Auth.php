<?php

declare(strict_types=1);

/**
 * Authentication language strings (English)
 */
return [
    // JWT messages
    'headerMissing'           => 'Authorization header missing',
    'invalidFormat'           => 'Invalid authorization header format',
    'invalidToken'            => 'Invalid or expired token',
    'tokenRevoked'            => 'Token has been revoked',
    'appKeyMissing'           => 'X-App-Key header is required',
    'appKeyInvalid'           => 'X-App-Key is invalid or inactive',
    'emailNotVerified'        => 'Email not verified',
    'accountPendingApproval'  => 'Account pending admin approval',
    'accountSetupRequired'    => 'Account setup required. Please check your invitation email to set your password.',
    'registrationPendingApproval' => 'Registration received. Please verify your email and wait for admin approval.',
    'registrationPendingApprovalNoVerification' => 'Registration received. Please wait for admin approval.',
    'googleTokenRequired'     => 'Google token is required',
    'googleInvalidToken'      => 'Invalid Google token',
    'googleEmailNotVerified'  => 'Google account email is not verified',
    'googleProviderMismatch'  => 'This email is linked to a different login provider',
    'googleProviderIdentityMismatch' => 'Google identity does not match the linked account',
    'googleUserMissing'       => 'User disappeared after Google update.',
    'googleRegistrationPendingApproval' => 'Google sign-in received. Your account is pending admin approval.',
    'googleClientNotConfigured' => 'Google authentication is not configured',
    'googleLibraryUnavailable' => 'Google authentication library is unavailable',

    // Authentication messages
    'authRequired'            => 'Authentication required',
    'insufficientPermissions' => 'Insufficient permissions',
    'unauthorized'            => 'Unauthorized access',
    'passwordResetSuccess'    => 'Password has been reset successfully',

    // Rate limiting
    'rateLimitExceeded'       => 'Rate limit exceeded. Please try again later.',
    'tooManyRequests'         => 'Too many requests. Maximum {0} requests per {1} seconds allowed.',
    'tooManyLoginAttempts'    => 'Too many authentication attempts. Maximum {0} attempts per {1} minutes. Please try again later.',
];
