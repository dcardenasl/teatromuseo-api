<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Email Verification Service Interface
 */
interface VerificationServiceInterface
{
    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(int $userId, ?SecurityContext $context = null, ?string $locale = null): bool;

    /**
     * Verify email with token
     */
    public function verifyEmail(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Resend verification email
     */
    public function resendVerification(int $userId, ?SecurityContext $context = null, ?string $locale = null): bool;
}
