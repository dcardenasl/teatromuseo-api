<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserCreateRequestDTO;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\UserRoleAssignmentService;
use CodeIgniter\Events\Events;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

class CreateUserAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected UserRoleAssignmentService $userRoleAssignmentService
    ) {
    }

    public function execute(UserCreateRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        $context ??= SecurityContext::anonymous();
        $adminId = $context->user_id;
        // Only an actor with users.write permission can create directly-active users;
        // anyone else (including unauthenticated registration) creates invited records.
        $isPrivilegedCreator = $context->hasPermission('users.write');
        $status = $isPrivilegedCreator ? 'active' : 'invited';
        $now = date('Y-m-d H:i:s');
        $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';

        $data = [
            'email'       => $request->email,
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'password'    => password_hash($generatedPassword, PASSWORD_BCRYPT),
            'status'      => $status,
            'approved_at' => $status === 'active' ? $now : null,
            'approved_by' => $isPrivilegedCreator ? $adminId : null,
            'invited_at'  => $now,
            'invited_by'  => $adminId,
        ];

        $userId = $this->userRepository->insert($data);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userRepository->errors());
        }

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['user' => lang('Api.resourceNotFound')]);
        }

        $roleIds = $request->role_ids;
        if ($roleIds !== []) {
            $this->userRoleAssignmentService->syncRoles((int) $userId, $roleIds, $adminId);
        } else {
            $this->userRoleAssignmentService->assignRoleByCode((int) $userId, 'user', $adminId);
        }

        // Trigger Domain Event for side effects (email, logs, etc.)
        Events::trigger('user.created', $user, $context, $request->locale ?? null);

        return $user;
    }
}
