<?php

declare(strict_types=1);

use dcardenasl\Ci4ApiCore\Security\Hasher;

if (! function_exists('is_email_verification_required')) {
    function is_email_verification_required(): bool
    {
        return Hasher::isEmailVerificationRequired();
    }
}

if (! function_exists('hash_token')) {
    function hash_token(string $token): string
    {
        return Hasher::token($token);
    }
}
