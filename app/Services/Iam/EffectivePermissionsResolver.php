<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Models\PermissionModel;
use App\Models\UserRoleModel;
use CodeIgniter\Cache\CacheInterface;
use dcardenasl\Ci4ApiCore\Contracts\Iam\PermissionResolverInterface;

/**
 * Resolves the set of effective permission codes a user has within an
 * application by walking user_roles → role_permissions → permissions
 * (filtered by the target application).
 *
 * Roles are global (cross-app); permissions belong to a specific application.
 * The effective permissions for (user, app) are all permissions of all the
 * user's roles whose application_id matches the requested app.
 *
 * Results are cached for 60 seconds. Use invalidateForUser() after changes to
 * a specific user's roles; invalidateAll() when role/permission mappings
 * change globally (covers the cross-app cache fan-out cheaply).
 */
class EffectivePermissionsResolver implements PermissionResolverInterface
{
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly UserRoleModel $userRoleModel,
        private readonly PermissionModel $permissionModel,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return list<string> permission codes (sorted, deduplicated)
     * @phpstan-ignore dtoFirst.arrayReturn
     */
    public function resolve(int $userId, int $applicationId): array
    {
        $cacheKey = self::cacheKey($userId, $applicationId);

        /** @var list<string>|null $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $codes = $this->load($userId, $applicationId);
        $this->cache->save($cacheKey, $codes, self::CACHE_TTL);

        return $codes;
    }

    /**
     * @return list<string> all permission codes across all applications (sorted, deduplicated)
     * @phpstan-ignore dtoFirst.arrayReturn
     */
    public function resolveAll(int $userId): array
    {
        $cacheKey = self::allCacheKey($userId);

        /** @var list<string>|null $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $codes = $this->loadAll($userId);
        $this->cache->save($cacheKey, $codes, self::CACHE_TTL);

        return $codes;
    }

    public function invalidateForUser(int $userId, int $applicationId): void
    {
        $this->cache->delete(self::cacheKey($userId, $applicationId));
        $this->cache->delete(self::allCacheKey($userId));
    }

    public function invalidateAll(): void
    {
        $this->cache->deleteMatching('iam_eff_perms_*');
    }

    /**
     * @return list<string>
     */
    private function load(int $userId, int $applicationId): array
    {
        if ($this->userIsSuperadmin($userId)) {
            return $this->permissionModel->findCodesByApplication($applicationId);
        }

        return $this->userRoleModel->getPermissionCodesForUserAndApplication($userId, $applicationId);
    }

    private function userIsSuperadmin(int $userId): bool
    {
        return $this->userRoleModel->userHasRoleCode($userId, 'superadmin');
    }

    /**
     * @return list<string>
     */
    private function loadAll(int $userId): array
    {
        if ($this->userIsSuperadmin($userId)) {
            return $this->permissionModel->findAllCodes();
        }

        return $this->userRoleModel->getPermissionCodesForUser($userId);
    }

    private static function cacheKey(int $userId, int $applicationId): string
    {
        return "iam_eff_perms_{$userId}_{$applicationId}";
    }

    private static function allCacheKey(int $userId): string
    {
        return "iam_eff_perms_all_{$userId}";
    }
}
