<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\ListUserPermissionsRequestDTO;
use App\DTO\Response\Iam\ApplicationSummary;
use App\DTO\Response\Iam\UserPermissionsResponseDTO;
use App\Models\ApplicationModel;
use App\Models\UserModel;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

class UserPermissionsService
{
    public function __construct(
        private readonly EffectivePermissionsResolver $resolver,
        private readonly UserModel $userModel,
        private readonly ApplicationModel $applicationModel,
    ) {
    }

    public function listForUser(
        int $userId,
        ListUserPermissionsRequestDTO $request,
        ?SecurityContext $context = null
    ): UserPermissionsResponseDTO {
        unset($context);

        if (! $this->userExists($userId)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $application = $this->findApplicationByCode($request->app);
        if ($application === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $permissions = $this->resolver->resolve($userId, $application->id);

        return new UserPermissionsResponseDTO(
            user_id: $userId,
            application: $application,
            permissions: $permissions,
        );
    }

    private function userExists(int $userId): bool
    {
        // UserModel::useSoftDeletes excludes soft-deleted rows automatically.
        return $this->userModel->find($userId) !== null;
    }

    private function findApplicationByCode(string $code): ?ApplicationSummary
    {
        $application = $this->applicationModel->findByCode($code);
        if ($application === null) {
            return null;
        }

        return new ApplicationSummary(
            id: (int) $application->id,
            code: (string) $application->code,
            name: (string) $application->name,
        );
    }
}
