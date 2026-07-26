<?php

declare(strict_types=1);

/**
 * Password reset language strings (English)
 */
return [
    // Success messages
    'linkSent'           => 'If an account exists with that email, a password reset link has been sent.',
    'tokenValid'         => 'Token is valid',
    'passwordReset'      => 'Your password has been reset successfully',

    // Error messages
    'invalidToken'             => 'Invalid or expired reset token',
    'userNotFound'             => 'User not found',

    // Response data messages
    'sentMessage'        => 'Password reset link sent',
    'resetMessage'       => 'Password reset successfully',
    'reactivationRequested' => 'Account reactivation requested and pending admin approval',
];
