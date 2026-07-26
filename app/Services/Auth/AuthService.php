<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\Request\Auth\UpdateMeRequestDTO;
use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\MeResponseDTO;
use App\DTO\Response\Auth\RegisterResponseDTO;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Actions\GoogleLoginAction;
use App\Services\Auth\Actions\RegisterUserAction;
use App\Services\Auth\Support\SessionManager;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Users\Actions\UpdateSelfProfileAction;
use App\Services\Users\UserAccountGuard;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Security\Token;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * AuthService (Refactored)
 *
 * Handles user authentication and registration by orchestrating specialized components.
 */
class AuthService implements \App\Interfaces\Auth\AuthServiceInterface
{
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    protected RegisterUserAction $registerUserAction;
    protected GoogleLoginAction $googleLoginAction;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        RegisterUserAction $registerUserAction,
        GoogleLoginAction $googleLoginAction,
        protected AuditServiceInterface $auditService,
        protected SessionManager $sessionManager,
        protected EffectivePermissionsResolver $permissionsResolver,
        protected UserAccountGuard $userAccessPolicy,
        protected UpdateSelfProfileAction $updateSelfProfileAction,
        protected bool $allowTestPasswordBypass = false
    ) {
        $this->registerUserAction = $registerUserAction;
        $this->googleLoginAction = $googleLoginAction;
    }

    /**
     * Authenticate user with credentials
     */
    public function login(LoginRequestDTO $request, ?SecurityContext $context = null): LoginResponseDTO
    {
        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userRepository->findByEmail($request->email);

        // Use a constant time comparison to prevent timing attacks
        $storedHash = $user ? (string) $user->password : '$2y$10$fakeHashToPreventTimingAttacksByEnsuringConstantTimeResponse1234567890';

        $passwordValid = false;

        if ($this->allowTestPasswordBypass) {
            // Environment check is done at injection time (AuthIdentityServices), but we double-check here for extra safety
            if (ENVIRONMENT === 'testing') {
                // High-entropy test secret to prevent accidental use
                $testSecret = 'SKIP_VERIFY_99_ae_7b_21_42_8c';
                $passwordValid = Token::constantTimeCompare($testSecret, $request->password);
            } else {
                log_message('critical', '[AuthService] TEST PASSWORD BYPASS ATTEMPTED OUTSIDE TESTING ENVIRONMENT. IP: ' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
        }

        if (!$passwordValid) {
            $passwordValid = password_verify($request->password, $storedHash);
        }

        if (!$user || !$passwordValid) {
            $this->auditService->log('login_failure', 'users', $user ? (int) $user->id : null, ['email' => $request->email], ['reason' => 'invalid_credentials'], $context);
            throw new AuthenticationException(lang('Users.auth.invalidCredentials'), ['credentials' => lang('Users.auth.invalidCredentials')]);
        }

        // Elevate context for successful login audit
        $userContext = new SecurityContext((int) $user->id, $context !== null ? $context->metadata : []);
        $this->auditService->log('login_success', 'users', (int) $user->id, [], ['email' => (string) $user->email], $userContext);

        $this->userAccessPolicy->assertCanAuthenticate($user);

        return $this->sessionManager->generateSessionResponse($user);
    }

    /**
     * Authenticate user with Google ID token
     */
    public function loginWithGoogleToken(GoogleLoginRequestDTO $request, ?SecurityContext $context = null): OperationResult
    {
        return $this->googleLoginAction->execute($request, $context);
    }

    /**
     * Get current authenticated user profile
     */
    public function me(int $user_id, ?SecurityContext $context = null): MeResponseDTO
    {
        if ($user_id <= 0) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $user = $this->userRepository->find($user_id);
        if (!$user) {
            throw new AuthenticationException(lang('Users.auth.notAuthenticated'));
        }

        return MeResponseDTO::fromUserData(
            $user->toArray(),
            $this->permissionsResolver->resolveAll($user_id)
        );
    }

    /**
     * Register a new user with password
     */
    public function register(RegisterRequestDTO $request, ?SecurityContext $context = null): RegisterResponseDTO
    {
        return $this->wrapInTransaction(function () use ($request, $context) {
            $user = $this->registerUserAction->execute($request, $context);
            return RegisterResponseDTO::fromArray($user->toArray());
        });
    }

    /**
     * Update the authenticated user's own profile.
     *
     * Authorization is implicit: the subject id is taken from the security
     * context — the caller cannot target another user. Allowlist is enforced
     * by `UpdateMeRequestDTO` (only first_name, last_name, avatar_url).
     */
    public function updateMe(UpdateMeRequestDTO $request, ?SecurityContext $context = null): MeResponseDTO
    {
        if ($context === null || $context->user_id === null) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $userId = $context->user_id;

        return $this->wrapInTransaction(function () use ($userId, $request) {
            $updatedUser = $this->updateSelfProfileAction->execute($userId, $request);

            return MeResponseDTO::fromUserData(
                $updatedUser->toArray(),
                $this->permissionsResolver->resolveAll($userId)
            );
        });
    }
}
