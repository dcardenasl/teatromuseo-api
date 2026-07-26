<?php

declare(strict_types=1);

namespace App\Repositories\Tokens;

use App\Entities\ApiKeyEntity;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Models\ApiKeyModel;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;

/**
 * API key repository implementation.
 *
 * @extends BaseRepository<ApiKeyEntity>
 */
class ApiKeyRepository extends BaseRepository implements ApiKeyRepositoryInterface
{
    public function __construct(ApiKeyModel $model)
    {
        parent::__construct($model);
    }

    public function findByHash(string $hash): ?ApiKeyEntity
    {
        /** @var ApiKeyEntity|null $entity */
        $entity = $this->model->where('key_hash', $hash)->first();

        return $entity;
    }
}
