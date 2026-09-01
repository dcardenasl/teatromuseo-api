<?php

declare(strict_types=1);

/**
 * Token management language strings (English)
 */
return [
    // Refresh tokens
    'refreshTokenRequired'  => 'Refresh token is required',
    'invalidRefreshToken'   => 'Invalid or expired refresh token',
    'refreshTokenRevoked'   => 'Refresh token revoked successfully',
    'tokenNotFound'         => 'Token not found',
    'allTokensRevoked'      => 'All refresh tokens revoked successfully',

    // Token revocation
    'revocationFailed'             => 'Failed to revoke token',
    'tokenRevokedSuccess'          => 'Token revoked successfully',
    'allUserTokensRevoked'         => 'All user tokens revoked successfully',
    'authorizationHeaderRequired'  => 'Authorization header is required',
    'invalidAuthorizationFormat'   => 'Invalid Authorization header format. Expected: Bearer <token>',
    'invalidToken'                 => 'Invalid token',
    'tokenDecodeFailed'            => 'Token could not be decoded',
    'missingRequiredClaims'        => 'Token missing required claims (jti, exp)',

    // Configuration
    'issuerRequired'        => 'JWT issuer (baseURL) is required. This is usually set via app.baseURL in .env',

    // General
    'invalidRequest'        => 'Invalid request',
    'notFound'              => 'Not found',
    'userNotFound'          => 'User not found',
    'accessTokenVersionUserNotFound' => 'Cannot resolve the access-token version for an unknown user.',
    'accessTokenVersionInvalidUserId' => 'User id must be positive.',
    'accessTokenVersionIncrementFailed' => 'Cannot increment the access-token version for an unknown user.',
];
