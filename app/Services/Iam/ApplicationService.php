<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Entities\ApplicationEntity;
use App\Interfaces\Iam\ApplicationServiceInterface;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * @extends BaseCrudService<ApplicationEntity>
 */
class ApplicationService extends BaseCrudService implements ApplicationServiceInterface
{
    /**
     * @param RepositoryInterface<ApplicationEntity> $applicationRepository
     */
    public function __construct(
        RepositoryInterface $applicationRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($applicationRepository, $responseMapper);
    }
}
