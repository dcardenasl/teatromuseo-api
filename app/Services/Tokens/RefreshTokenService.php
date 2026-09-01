<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\Enums\RefreshTokenRevocationReason;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\TokenVersionServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\Iam\EffectivePermissionsResolver;
use App\Services\Users\UserAccountGuard;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Security\Token;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

/**
 * Refresh Token Service
 *
 * Manages refresh token lifecycle (issue, refresh, revoke)
 */
readonly class RefreshTokenService implements \App\Interfaces\Tokens\RefreshTokenServiceInterface
{
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    public function __construct(
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected UserModel $userModel,
        protected UserAccountGuard $userAccountGuard,
        protected EffectivePermissionsResolver $permissionsResolver,
        protected AuditServiceInterface $auditService,
        protected TokenVersionServiceInterface $tokenVersionService,
        protected int $refreshTokenTtl = 604800,
        protected int $accessTokenTtl = 3600
    ) {
    }

    /**
     * Issue a new refresh token
     */
    public function issueRefreshToken(int $userId): string
    {
        return $this->issueRefreshTokenForFamily($userId, bin2hex(random_bytes(16)), null);
    }

    /**
     * Issue a token in an existing family after a successful rotation.
     */
    private function issueRefreshTokenForFamily(int $userId, string $familyId, ?int $parentId): string
    {
        $token = Token::generate();
        $tokenHash = Hasher::token($token);

        $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTokenTtl);

        $this->refreshTokenModel->insert([
            'user_id'    => $userId,
            'token'      => $tokenHash,
            'family_id'  => $familyId,
            'parent_id'  => $parentId,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Refresh access token using refresh token (with rotation)
     */
    public function refreshAccessToken(
        RefreshTokenRequestDTO $request,
        ?\dcardenasl\Ci4ApiCore\Dto\SecurityContext $context = null
    ): \App\DTO\Response\Identity\TokenResponseDTO {
        $reuseDetected = false;
        $reuseUserId = null;
        $reuseFamilyId = null;

        $result = $this->wrapInTransaction(function () use ($request, &$reuseDetected, &$reuseUserId, &$reuseFamilyId) {
            $tokenRecord = $this->refreshTokenModel->findForUpdate($request->refresh_token);

            if (!$tokenRecord || !isset($tokenRecord->user_id)) {
                throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
            }

            if (strtotime((string) ($tokenRecord->expires_at ?? '')) <= time()) {
                throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
            }

            $userId = (int) $tokenRecord->user_id;
            $familyId = trim((string) ($tokenRecord->family_id ?? ''));
            if ($familyId === '') {
                throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
            }

            if (($tokenRecord->revoked_at ?? null) !== null) {
                if (($tokenRecord->revoked_reason ?? null) === RefreshTokenRevocationReason::Rotated->value) {
                    // A rotated token was replayed. Revoke every refresh
                    // session for this user and invalidate all access JWTs.
                    $this->refreshTokenModel->revokeAllUserTokens(
                        $userId,
                        RefreshTokenRevocationReason::ReuseDetected
                    );
                    $this->tokenVersionService->increment($userId);
                    $reuseDetected = true;
                    $reuseUserId = $userId;
                    $reuseFamilyId = $familyId;

                    return null;
                }

                throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
            }

            // Revoke old refresh token (token rotation security)
            $this->refreshTokenModel->revokeToken(
                $request->refresh_token,
                RefreshTokenRevocationReason::Rotated
            );

            // Issue new refresh token
            $newRefreshToken = $this->issueRefreshTokenForFamily(
                $userId,
                $familyId,
                (int) ($tokenRecord->id ?? 0)
            );

            // Validate user account status
            $user = $this->userModel->find($tokenRecord->user_id);
            if (!$user instanceof \App\Entities\UserEntity) {
                throw new AuthenticationException(lang('Tokens.userNotFound'));
            }

            $this->userAccountGuard->assertCanAuthenticate($user);

            // Generate new access token
            $permissions = $this->permissionsResolver->resolveAll((int) $user->id);
            $accessToken = $this->jwtService->encode(
                (int) $user->id,
                $permissions,
                max(0, (int) ($user->auth_token_version ?? 0))
            );

            return \App\DTO\Response\Identity\TokenResponseDTO::fromArray([
                'access_token'  => $accessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in'    => $this->accessTokenTtl,
                'user'          => \App\DTO\Response\Auth\MeResponseDTO::fromUserData(
                    $user->toArray(),
                    $permissions
                ),
            ]);
        });

        if ($reuseDetected) {
            try {
                $this->auditService->log(
                    'revoked_token_reuse_detected',
                    'tokens',
                    $reuseUserId,
                    [],
                    [
                        'user_id' => $reuseUserId,
                        'family_id' => $reuseFamilyId,
                    ],
                    $context,
                    'denied',
                    'critical'
                );
            } catch (\Throwable $exception) {
                log_message('error', 'Unable to persist refresh-token reuse audit event: ' . $exception->getMessage());
            }

            throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
        }

        if (!$result instanceof \App\DTO\Response\Identity\TokenResponseDTO) {
            throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
        }

        return $result;
    }

    /**
     * Revoke a refresh token
     */
    public function revoke(RefreshTokenRequestDTO $request): OperationResult
    {
        $revoked = $this->refreshTokenModel->revokeToken($request->refresh_token);

        if (!$revoked) {
            throw new \dcardenasl\Ci4ApiCore\Exceptions\NotFoundException(lang('Tokens.tokenNotFound'));
        }

        return OperationResult::success(null, lang('Tokens.refreshTokenRevoked'));
    }

    /**
     * Revoke all user's refresh tokens
     */
    public function revokeAllUserTokens(int $userId): OperationResult
    {
        $this->wrapInTransaction(function () use ($userId): void {
            $this->refreshTokenModel->revokeAllUserTokens(
                $userId,
                RefreshTokenRevocationReason::RevokeAll
            );
            $this->tokenVersionService->increment($userId);
        });

        return OperationResult::success(null, lang('Tokens.allTokensRevoked'));
    }
}
