<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\PermissionCreateRequestDTO;
use App\DTO\Request\Iam\PermissionUpdateRequestDTO;
use App\Entities\PermissionEntity;
use App\Interfaces\Iam\PermissionServiceInterface;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Libraries\Iam\SuperadminPermissionAttacher;
use CodeIgniter\Validation\ValidationInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Support\RelationLabelLoader;

/**
 * @extends BaseCrudService<PermissionEntity>
 */
class PermissionService extends BaseCrudService implements PermissionServiceInterface
{
    /**
     * @param RepositoryInterface<PermissionEntity> $permissionRepository
     */
    public function __construct(
        RepositoryInterface $permissionRepository,
        ResponseMapperInterface $responseMapper,
        private readonly IamAuthorizationService $authz,
        private readonly ValidationInterface $validation,
        private readonly ApiRequest $request,
        private readonly ApiKeyRepositoryInterface $apiKeyRepository,
        private readonly RelationLabelLoader $labels = new RelationLabelLoader()
    ) {
        parent::__construct($permissionRepository, $responseMapper);
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

    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        $this->authz->assertSuperAdmin($context);

        // Auto-assign application_id from context if not provided, or fallback to resolving from X-App-Key header
        if (empty($data['application_id'])) {
            if ($context !== null && $context->app_id !== null) {
                $data['application_id'] = $context->app_id;
            } else {
                $rawKey = $this->request->getHeaderLine('X-App-Key');
                if ($rawKey !== '') {
                    $hash = hash('sha256', $rawKey);
                    $appKey = $this->apiKeyRepository->findByHash($hash);
                    if ($appKey && $appKey->isActive()) {
                        $data['application_id'] = $appKey->application_id;
                    }
                }
            }
        }

        new PermissionCreateRequestDTO($data, $this->validation);

        return parent::beforeStore($data, $context);
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $this->authz->assertSuperAdmin($context);

        new PermissionUpdateRequestDTO($data, $this->validation);

        return parent::beforeUpdate($id, $data, $context);
    }

    protected function afterStore(object $entity, ?SecurityContext $context): void
    {
        $permissionId = isset($entity->id) ? (int) $entity->id : 0;
        (new SuperadminPermissionAttacher())->attach([$permissionId]);
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        $this->authz->assertSuperAdmin($context);

        parent::beforeDelete($id, $context);
    }
}
