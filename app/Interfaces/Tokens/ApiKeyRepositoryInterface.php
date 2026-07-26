<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

use App\Entities\ApiKeyEntity;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;

/**
 * @extends RepositoryInterface<ApiKeyEntity>
 */
interface ApiKeyRepositoryInterface extends RepositoryInterface
{
    public function findByHash(string $hash): ?ApiKeyEntity;
}
