<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\ListUserPermissionsRequestDTO;
use App\DTO\Response\Iam\ApplicationSummary;
use App\DTO\Response\Iam\UserPermissionsResponseDTO;
use CodeIgniter\Database\ConnectionInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

class UserPermissionsService
{
    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(
        private readonly EffectivePermissionsResolver $resolver,
        private readonly ConnectionInterface $db,
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
        $query = $this->db->table('users')
            ->select('id')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->get();

        if ($query === false) {
            return false;
        }

        return $query->getRowArray() !== null;
    }

    private function findApplicationByCode(string $code): ?ApplicationSummary
    {
        $query = $this->db->table('applications')
            ->select('id, code, name')
            ->where('code', $code)
            ->get();

        if ($query === false) {
            return null;
        }

        $row = $query->getRowArray();
        if ($row === null) {
            return null;
        }

        return new ApplicationSummary(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
        );
    }
}
