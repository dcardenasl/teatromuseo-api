<?php

declare(strict_types=1);

namespace App\Services\Tokens;

class BearerTokenService
{
    /**
     * Extract Bearer token from Authorization header.
     *
     * Returns null when the header is empty or invalid.
     */
    public function extractFromHeader(?string $authorizationHeader): ?string
    {
        if ($authorizationHeader === null || trim($authorizationHeader) === '') {
            return null;
        }

        if (! preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token !== '' ? $token : null;
    }
}
