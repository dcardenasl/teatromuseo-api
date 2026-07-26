<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\Request\Auth\UpdateMeRequestDTO;
use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\MeResponseDTO;
use App\DTO\Response\Auth\RegisterResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * Modernized Authentication Service Interface
 *
 * Enforces strict typing with self-validating DTOs.
 */
interface AuthServiceInterface
{
    /**
     * Authenticate user with credentials
     */
    public function login(LoginRequestDTO $request, ?SecurityContext $context = null): LoginResponseDTO;

    /**
     * Authenticate user with Google ID token
     */
    public function loginWithGoogleToken(GoogleLoginRequestDTO $request, ?SecurityContext $context = null): OperationResult;

    /**
     * Get the current authenticated user profile
     */
    public function me(int $user_id, ?SecurityContext $context = null): MeResponseDTO;

    /**
     * Register a new user with password
     */
    public function register(RegisterRequestDTO $request, ?SecurityContext $context = null): RegisterResponseDTO;

    /**
     * Update the authenticated user's own profile (allowlist: first_name, last_name, avatar_url).
     */
    public function updateMe(UpdateMeRequestDTO $request, ?SecurityContext $context = null): MeResponseDTO;
}
