<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reasons why a refresh token was invalidated.
 *
 * Persisting a closed set of reasons is what lets the refresh flow
 * distinguish a rotated token being replayed from an ordinary logout.
 */
enum RefreshTokenRevocationReason: string
{
    case Rotated = 'rotated';
    case Logout = 'logout';
    case RevokeAll = 'revoke_all';
    case ReuseDetected = 'reuse_detected';
}
