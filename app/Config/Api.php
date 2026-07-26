<?php

declare(strict_types=1);

namespace Config;

/**
 * Main API Configuration.
 *
 * Inherits all the env-driven defaults (JWT, rate limiting, search,
 * pagination, file uploads, monitoring, API versions) from
 * `dcardenasl\Ci4ApiCore\Config\Api` — see that class for the
 * complete list of properties and how they hydrate from environment
 * variables. Override or add fields here for app-specific tuning.
 */
class Api extends \dcardenasl\Ci4ApiCore\Config\Api
{
    /**
     * @var list<string>
     */
    public array $accessPolicyBypassRoutes = [
        'api/v1/auth/resend-verification',
    ];

    /**
     * Keep local development usable while retaining a strict production limit.
     */
    public int $authRateLimitRequests = ENVIRONMENT === 'development' ? 20 : 3;
    public int $authRateLimitWindow = ENVIRONMENT === 'development' ? 300 : 3600;
}
