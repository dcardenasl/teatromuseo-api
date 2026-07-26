<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\Interfaces\Tokens\AuthTokenServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Modernized Token Controller
 */
class TokenController extends ApiController
{
    protected AuthTokenServiceInterface $authTokenService;

    protected function resolveDefaultService(): object
    {
        $this->authTokenService = Services::authTokenService();

        return $this->authTokenService;
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(): ResponseInterface
    {
        return $this->handleRequest('refreshAccessToken', RefreshTokenRequestDTO::class);
    }

    /**
     * Revoke current access token
     */
    public function revoke(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            return $this->authTokenService->revokeToken(
                $this->request->getHeaderLine('Authorization'),
                $context
            );
        });
    }

    /**
     * Revoke all tokens for the current user
     */
    public function revokeAll(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            $userId = $context->user_id ?? 0;

            return $this->authTokenService->revokeAllUserTokens($userId, $context);
        });
    }
}
