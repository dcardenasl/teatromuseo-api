<?php

declare(strict_types=1);

namespace App\Filters;

use Config\Services;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractPermissionFilter;

/**
 * Starter-specific permission filter — delegates the entire policy
 * to `AbstractPermissionFilter` and only injects the consumer's
 * `SecurityAuditLogger`. Argument syntax remains `permission:<code>`
 * (e.g. `permission:users.write`).
 */
class PermissionFilter extends AbstractPermissionFilter
{
    protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface
    {
        return Services::securityAuditLogger();
    }
}
