<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Interfaces\Iam\ApplicationPermissionsResolverInterface;
use App\Models\PermissionModel;
use CodeIgniter\Cache\CacheInterface;

/**
 * Resolves the set of permission codes belonging to a given application.
 *
 * Used by service (M2M) tokens, which carry the application's full scope
 * — there is no user involved. Mirrors EffectivePermissionsResolver but
 * walks `permissions.application_id` directly.
 */
class ApplicationPermissionsResolver implements ApplicationPermissionsResolverInterface
{
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly PermissionModel $permissionModel,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return list<string> permission codes (sorted, deduplicated)
     * @phpstan-ignore dtoFirst.arrayReturn
     */
    public function resolve(int $applicationId): array
    {
        $cacheKey = self::cacheKey($applicationId);

        /** @var list<string>|null $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $codes = $this->permissionModel->findCodesByApplication($applicationId);
        $this->cache->save($cacheKey, $codes, self::CACHE_TTL);

        return $codes;
    }

    public function invalidate(int $applicationId): void
    {
        $this->cache->delete(self::cacheKey($applicationId));
    }

    private static function cacheKey(int $applicationId): string
    {
        return "iam_app_perms_{$applicationId}";
    }
}
