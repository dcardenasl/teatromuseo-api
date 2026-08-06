<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use App\DTO\Request\Iam\RoleCreateRequestDTO;
use App\DTO\Request\Iam\RoleUpdateRequestDTO;
use App\DTO\Response\Iam\PermissionResponseDTO;
use App\Entities\RoleEntity;
use App\Interfaces\Iam\RoleServiceInterface;
use App\Models\PermissionModel;
use App\Models\RolePermissionModel;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Support\RelationLabelLoader;

/**
 * @extends BaseCrudService<RoleEntity>
 */
class RoleService extends BaseCrudService implements RoleServiceInterface
{
    /**
     * @param RepositoryInterface<RoleEntity> $roleRepository
     */
    public function __construct(
        RepositoryInterface $roleRepository,
        ResponseMapperInterface $responseMapper,
        private readonly IamAuthorizationService $authz,
        private readonly RolePermissionAssignmentService $permissionAssignment,
        private readonly \CodeIgniter\Validation\ValidationInterface $validation,
        private readonly RolePermissionModel $rolePermissionModel,
        private readonly PermissionModel $permissionModel,
        private readonly RelationLabelLoader $labels = new RelationLabelLoader()
    ) {
        parent::__construct($roleRepository, $responseMapper);
    }

    /**
     * Override to consume `permission_ids` from RoleCreateRequestDTO and
     * sync the role↔permission M2M atomically in the same transaction.
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($request, $context) {
            $response = parent::store($request, $context);

            if ($request instanceof RoleCreateRequestDTO && $request->permission_ids !== null && $response instanceof \App\DTO\Response\Iam\RoleResponseDTO) {
                $this->permissionAssignment->syncPermissions(
                    $response->id,
                    new AttachPermissionsRequestDTO(['permission_ids' => $request->permission_ids], $this->validation),
                    $context
                );
                // Re-map so the caller sees a consistent post-sync entity.
                $response = $this->show($response->id, $context);
            }

            return $response;
        });
    }

    /**
     * Override to consume `permission_ids` from RoleUpdateRequestDTO. Unlike
     * the field set, `permission_ids` may be the only thing being updated,
     * so we short-circuit BaseCrudService::update's "no fields to update"
     * guard when only permissions changed.
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        if (! $request instanceof RoleUpdateRequestDTO) {
            return parent::update($id, $request, $context);
        }

        return $this->wrapInTransaction(function () use ($id, $request, $context) {
            $hasFieldUpdates       = $request->toArray() !== [];
            $hasPermissionUpdates  = $request->permission_ids !== null;

            if (! $hasFieldUpdates && ! $hasPermissionUpdates) {
                throw new \dcardenasl\Ci4ApiCore\Exceptions\BadRequestException(lang('Api.noFieldsToUpdate'));
            }

            if ($hasFieldUpdates) {
                parent::update($id, $request, $context);
            } else {
                // Run the same authz that beforeUpdate() would have applied.
                $this->ensureRoleExists($id);
                $this->authz->assertCanModifyRole($context, $id);
            }

            if ($hasPermissionUpdates) {
                $this->permissionAssignment->syncPermissions(
                    $id,
                    new AttachPermissionsRequestDTO(['permission_ids' => $request->permission_ids], $this->validation),
                    $context
                );
            }

            return $this->show($id, $context);
        });
    }

    protected function enrichEntities(array $entities): array
    {
        return $this->labels->attachLabel(
            $entities,
            sourceField: 'application_id',
            targetField: 'application_name',
            relatedTable: 'applications',
            relatedLabel: 'name'
        );
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $this->authz->assertCanModifyRole($context, $id);

        return parent::beforeUpdate($id, $data, $context);
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        $this->authz->assertCanModifyRole($context, $id);

        parent::beforeDelete($id, $context);
    }

    /**
     * List permissions attached to a role.
     *
     * @return PermissionResponseDTO[]
     */
    public function listPermissions(int $roleId, ?SecurityContext $context = null)
    {
        $this->ensureRoleExists($roleId);

        $rows = $this->rolePermissionModel->getDetailedPermissionsForRole($roleId);

        return array_map(static fn (array $row) => self::permissionFromRow($row), $rows);
    }

    /**
     * Attach a list of permissions to a role. Idempotent — already-attached
     * permissions are silently ignored.
     *
     * @return PermissionResponseDTO[] full list of attached permissions after the operation
     */
    public function attachPermissions(int $roleId, AttachPermissionsRequestDTO $request, ?SecurityContext $context = null)
    {
        return $this->wrapInTransaction(function () use ($roleId, $request, $context) {
            $this->ensureRoleExists($roleId);

            // Resolve permission codes to IDs if present
            $permissionIds = $request->permission_ids;
            if (!empty($request->permission_codes)) {
                $resolvedIds = $this->permissionModel->findIdsByCodes(array_values($request->permission_codes));
                $permissionIds = array_values(array_unique(array_merge($permissionIds, $resolvedIds)));
            }

            $this->authz->assertCanModifyRole($context, $roleId);
            $this->authz->assertCanGrantPermissions($context, $permissionIds);

            $existingIds = $this->rolePermissionModel->getPermissionIdsForRole($roleId);

            $toInsert = array_values(array_diff($permissionIds, $existingIds));

            if ($toInsert !== []) {
                $validIds = $this->permissionModel->findExistingIds($toInsert);

                if (count($validIds) !== count($toInsert)) {
                    throw new NotFoundException(lang('Api.resourceNotFound'));
                }

                $this->rolePermissionModel->insertPairs($roleId, $validIds);
            }

            return $this->listPermissions($roleId);
        });
    }

    /**
     * Remove a single permission from a role.
     */
    public function detachPermission(int $roleId, int $permissionId, ?SecurityContext $context = null): bool
    {
        return $this->wrapInTransaction(function () use ($roleId, $permissionId, $context) {
            $this->ensureRoleExists($roleId);
            $this->authz->assertCanModifyRole($context, $roleId);
            $this->authz->assertCanGrantPermissions($context, [$permissionId]);

            $this->rolePermissionModel->deletePair($roleId, $permissionId);

            return true;
        });
    }

    private function ensureRoleExists(int $roleId): void
    {
        $role = $this->repository->find($roleId);
        if ($role === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function permissionFromRow(array $row): PermissionResponseDTO
    {
        return new PermissionResponseDTO(
            id: (int) $row['id'],
            application_id: (int) $row['application_id'],
            code: (string) $row['code'],
            resource: (string) $row['resource'],
            action: (string) $row['action'],
            description: (string) ($row['description'] ?? ''),
            application_name: isset($row['application_name']) ? (string) $row['application_name'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
