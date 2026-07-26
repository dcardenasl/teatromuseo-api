<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\UserRoleAssignmentService;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Security\Hasher;

/**
 * Google Auth Handler
 *
 * Manages the specific lifecycle of users authenticating via Google OAuth.
 */
class GoogleAuthHandler
{
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected UserRoleAssignmentService $userRoleAssignmentService
    ) {
    }

    /**
     * Create a new user in pending state from Google identity
     *
     * @param array<string, mixed> $identity
     */
    public function createPendingUser($identity): \App\Entities\UserEntity
    {
        $requiresVerification = Hasher::isEmailVerificationRequired();
        $status = $requiresVerification ? 'pending_approval' : 'active';
        $now = date('Y-m-d H:i:s');

        $userId = $this->userRepository->insert([
            'email' => strtolower(trim((string) $identity['email'])),
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'role' => 'user',
            'status' => $status,
            'oauth_provider' => 'google',
            'oauth_provider_id' => $identity['provider_id'],
            'email_verified_at' => $now,
            'approved_at' => $status === 'active' ? $now : null,
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userRepository->errors());
        }

        /** @var \App\Entities\UserEntity $user */
        $user = $this->userRepository->find((int) $userId);

        $this->userRoleAssignmentService->assignRoleByCode((int) $userId, 'user');

        return $user;
    }

    /**
     * Reactivate a soft-deleted user coming from Google
     *
     * @param array<string, mixed> $identity
     */
    public function reactivateDeletedUser(object $user, $identity): \App\Entities\UserEntity
    {
        return $this->wrapInTransaction(function () use ($user, $identity) {
            $requiresVerification = Hasher::isEmailVerificationRequired();
            $status = $requiresVerification ? 'pending_approval' : 'active';
            $now = date('Y-m-d H:i:s');

            $this->userRepository->restore((int) $user->id, [
                'status' => $status,
                'oauth_provider' => 'google',
                'oauth_provider_id' => $identity['provider_id'],
                'email_verified_at' => $now,
                'approved_at' => $status === 'active' ? $now : null,
            ]);

            $this->syncProfileIfEmpty((int) $user->id, $identity);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            /** @var \App\Entities\UserEntity|null $updatedUser */
            $updatedUser = $this->userRepository->find((int) $user->id);

            if (!$updatedUser instanceof \App\Entities\UserEntity) {
                // If not found by standard find (e.g. restoration took a moment or failed quietly),
                // we try to find it with deleted just to satisfy the return type contract,
                // but this shouldn't normally happen.
                $withDeleted = $this->userRepository->getModel()->withDeleted()->find((int) $user->id);
                if ($withDeleted instanceof \App\Entities\UserEntity) {
                    return $withDeleted;
                }

                throw new \RuntimeException(lang('Auth.googleUserMissing'));
            }

            return $updatedUser;
        });
    }

    /**
     * Synchronize profile data if the database record has empty fields
     *
     * @param array<string, mixed> $identity
     */
    public function syncProfileIfEmpty(int $userId, $identity): void
    {
        $currentUser = $this->userRepository->find($userId);
        if (!$currentUser) {
            return;
        }

        $updateData = array_filter([
            'first_name' => empty($currentUser->first_name) ? ($identity['first_name'] ?? null) : null,
            'last_name' => empty($currentUser->last_name) ? ($identity['last_name'] ?? null) : null,
            'avatar_url' => empty($currentUser->avatar_url) ? ($identity['avatar_url'] ?? null) : null,
        ]);

        if ($updateData !== []) {
            $this->userRepository->update($userId, $updateData);
        }
    }
}
