<?php

declare(strict_types=1);

namespace App\Interfaces\Iam;

use dcardenasl\Ci4ApiCore\Contracts\Iam\ApplicationPermissionResolverInterface;

interface ApplicationPermissionsResolverInterface extends ApplicationPermissionResolverInterface
{
    /**
     * Return all permission codes scoped to the given application.
     *
     * Used to issue service (M2M) tokens, where the calling app — not a
     * user — drives the JWT scope. Codes are sorted and deduplicated.
     *
     * @return list<string>
     */
    public function resolve(int $applicationId): array;

    public function invalidate(int $applicationId): void;
}
