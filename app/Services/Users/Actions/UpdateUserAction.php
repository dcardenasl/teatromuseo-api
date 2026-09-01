<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\Interfaces\Files\FileReferenceRepositoryInterface;
use App\Interfaces\Files\FileRepositoryInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\IamAuthorizationService;
use App\Services\Iam\UserRoleAssignmentService;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class UpdateUserAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected UserRoleAssignmentService $userRoleAssignmentService,
        protected IamAuthorizationService $authz,
        protected FileRepositoryInterface $fileRepository,
        protected FileReferenceRepositoryInterface $fileReferenceRepository,
    ) {
    }

    public function execute(int $userId, UserUpdateRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->userRepository->find($userId);
        if ($targetUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $updateData = $this->buildUpdateData($request);
        $hasFieldUpdates = $updateData !== [];
        $hasRoleUpdates = $request->role_ids !== null;

        if (! $hasFieldUpdates && ! $hasRoleUpdates) {
            throw new ValidationException(lang('Api.validationFailed'), ['update' => lang('Api.noFieldsToUpdate')]);
        }

        // Email is the security anchor of the account. Only superadmin may
        // change it via the admin endpoint; anyone else attempting to do so
        // gets a 403, not a silent drop. Self-edit of email is impossible
        // here too because PUT /users/{id} is already gated by assertNotSelf.
        if (array_key_exists('email', $updateData)
            && $targetUser->email !== $updateData['email']
            && ! $this->authz->isSuperAdmin($context)
        ) {
            throw new AuthorizationException(lang('Iam.cannotModifyEmail'));
        }

        if ($hasFieldUpdates) {
            $this->userRepository->update($userId, $updateData);
        }

        if ($hasRoleUpdates) {
            $this->userRoleAssignmentService->syncRoles(
                $userId,
                $request->role_ids ?? [],
                $context?->user_id
            );
        }

        /** @var \App\Entities\UserEntity|null $updatedUser */
        $updatedUser = $this->userRepository->find($userId);
        if ($updatedUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        if ($hasFieldUpdates && array_key_exists('avatar_url', $updateData)) {
            $this->syncAvatarReference($userId, $updateData['avatar_url'], $updatedUser);
        }

        return $updatedUser;
    }

    private function syncAvatarReference(int $userId, ?string $avatarUrl, \App\Entities\UserEntity $user): void
    {
        $this->fileReferenceRepository->unregisterByResource('User', $userId, 'avatar');

        if ($avatarUrl === null || $avatarUrl === '') {
            return;
        }

        $file = $this->fileRepository->findByUrl($avatarUrl);
        if ($file !== null) {
            $label = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $this->fileReferenceRepository->register((int) $file->id, 'User', $userId, 'avatar', $label ?: null);
        }
    }

    /**
     * $request->toArray() already carries email/first_name/last_name/
     * avatar_url with correct explicit-null-clears semantics (see
     * UserUpdateRequestDTO::map()'s docblock). password is deliberately
     * excluded from toArray() and handled here directly: a null password
     * (whether omitted or explicitly sent) is never persisted — only a
     * non-null value is hashed and written.
     *
     * @return array<string, mixed>
     */
    private function buildUpdateData(UserUpdateRequestDTO $request): array
    {
        $data = $request->toArray();

        if ($request->password !== null) {
            $data['password'] = password_hash($request->password, PASSWORD_BCRYPT);
        }

        return $data;
    }
}
