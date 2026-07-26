<?php

declare(strict_types=1);

/**
 * User-related language strings (English)
 */
return [
    // General messages
    'notFound'        => 'User not found',
    'idRequired'      => 'User ID is required',
    'emailRequired'   => 'Email is required',
    'passwordRequired' => 'Password is required',
    'adminPasswordForbidden' => 'Administrators cannot set passwords when creating users. An invitation email will be sent.',
    'fieldRequired'   => 'At least one field (email, first name, last name, password, role) is required',

    // Success messages
    'deletedSuccess'  => 'User deleted successfully',
    'invitationSent'  => 'Invitation email sent successfully',
    'approvedSuccess' => 'User approved successfully',
    'alreadyApproved' => 'User is already approved',
    'cannotApproveInvited' => 'Invited users are already approved and must complete password setup via invitation link.',
    'invalidApprovalState' => 'User cannot be approved from the current state.',
    'adminCannotManagePrivileged' => 'Admins can only manage users with role user.',
    'adminCannotAssignPrivilegedRole' => 'Admins cannot assign admin or superadmin roles.',

    // Error messages
    'deleteError'     => 'Error deleting user',
    'createError'     => 'Failed to create user',

    // Authentication
    'auth' => [
        'invalidCredentials' => 'Invalid credentials',
        'loginSuccess'       => 'Login successful',
        'registerSuccess'    => 'User registered successfully',
        'notAuthenticated'   => 'User not authenticated',
        'credentialsRequired' => 'Email and password are required',
    ],
];
