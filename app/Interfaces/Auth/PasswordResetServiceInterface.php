<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Password Reset Service Interface
 */
interface PasswordResetServiceInterface
{
    /**
     * Send password reset link to email
     */
    public function sendResetLink(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Validate reset token
     */
    public function validateToken(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Reset password using token
     */
    public function resetPassword(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;
}
