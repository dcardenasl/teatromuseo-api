<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\DTO\Request\Identity\VerificationRequestDTO;
use App\Interfaces\Auth\VerificationServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Modernized Verification Controller
 */
class VerificationController extends ApiController
{
    protected VerificationServiceInterface $verificationService;

    protected function resolveDefaultService(): object
    {
        $this->verificationService = Services::verificationService();

        return $this->verificationService;
    }

    /**
     * Verify email with token
     */
    public function verify(): ResponseInterface
    {
        return $this->handleRequest('verifyEmail', VerificationRequestDTO::class);
    }

    /**
     * Resend verification email
     */
    public function resend(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            $userId = $context->user_id ?? 0;
            $locale = $this->request->getVar('locale');

            return $this->verificationService->resendVerification(
                $userId,
                $context,
                is_string($locale) ? $locale : null
            );
        });
    }
}
