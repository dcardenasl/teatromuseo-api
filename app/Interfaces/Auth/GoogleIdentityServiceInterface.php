<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

/**
 * Google Identity Service Interface
 *
 * Verifies Google ID tokens and returns normalized identity claims.
 */
interface GoogleIdentityServiceInterface
{
    /**
     * Verify a Google ID token and return normalized claims.
     */
    public function verifyIdToken(string $id_token): \App\DTO\Response\Identity\GoogleIdentityResponseDTO;
}
