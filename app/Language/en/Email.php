<?php

declare(strict_types=1);

/**
 * Email content language strings (English)
 */
return [
    // Verification email
    'verification' => [
        'subject'     => 'Verify Your Email Address',
        'title'       => 'Verify Your Email',
        'welcome'     => 'Welcome to {0}!',
        'greeting'    => 'Hello, {0}!',
        'intro'       => 'Thank you for registering. To complete your registration, please verify your email address. Your account will be activated after admin approval.',
        'buttonText'  => 'Verify Email Address',
        'linkIntro'   => 'Or copy and paste this link into your browser:',
        'expiration'  => 'This link expires on {0}',
        'footer'      => 'If you did not create an account, you can safely ignore this email.',
        'autoMessage' => 'This is an automated message, please do not reply.',
        'copyright'   => 'All rights reserved.',
    ],

    // Password reset email
    'passwordReset' => [
        'subject'        => 'Reset Your Password',
        'title'          => 'Password Reset Request',
        'greeting'       => 'Hello!',
        'intro'          => 'You are receiving this email because we received a password reset request for your account.',
        'buttonText'     => 'Reset Password',
        'linkIntro'      => 'Or copy and paste this link into your browser:',
        'expiration'     => 'This password reset link expires in {0}',
        'securityTitle'  => 'Security Notice',
        'securityNotice' => 'If you did not request a password reset, please ignore this email or contact support if you have concerns about your account security.',
        'autoMessage'    => 'This is an automated message, please do not reply.',
        'copyright'      => 'All rights reserved.',
    ],

    // Invitation email
    'invitation' => [
        'subject'     => 'You Are Invited',
        'title'       => 'Your Account Invitation',
        'greeting'    => 'Hello, {0}!',
        'intro'       => 'An administrator created an account for you. Please set your password to activate access.',
        'googleOption' => 'You can also sign in directly with Google using this same email address.',
        'buttonText'  => 'Set Password',
        'linkIntro'   => 'Or copy and paste this link into your browser:',
        'expiration'  => 'This link expires in {0}',
        'autoMessage' => 'This is an automated message, please do not reply.',
        'copyright'   => 'All rights reserved.',
    ],

    // Account approved email
    'accountApproved' => [
        'subject'     => 'Your Account Has Been Approved',
        'title'       => 'Account Approved',
        'greeting'    => 'Hello, {0}!',
        'intro'       => 'Your account has been approved. You can now sign in.',
        'buttonText'  => 'Go to Login',
        'linkIntro'   => 'Or copy and paste this link into your browser:',
        'autoMessage' => 'This is an automated message, please do not reply.',
        'copyright'   => 'All rights reserved.',
    ],

    // Google login pending approval
    'pendingApprovalGoogle' => [
        'subject'     => 'Your account is pending admin approval',
        'title'       => 'Account Pending Approval',
        'greeting'    => 'Hello, {0}!',
        'intro'       => 'We received your Google sign-in. Your account is pending approval by an administrator.',
        'nextStep'    => 'You will receive another email as soon as your account is approved.',
        'autoMessage' => 'This is an automated message, please do not reply.',
        'copyright'   => 'All rights reserved.',
    ],
];
