<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\Response\Auth\ServiceTokenResponseDTO;

interface ServiceTokenServiceInterface
{
    /**
     * Issue a service (M2M) JWT for the application bound to the given API key.
     *
     * The raw key is re-resolved against the api_keys table (the filter has
     * already validated it; this lookup hits the same cache). Throws
     * AuthorizationException (403) when the key is missing, inactive, or
     * not bound to an application; throws NotFoundException (404) when the
     * application has been deleted.
     */
    public function issue(string $rawAppKey): ServiceTokenResponseDTO;

    /**
     * Same as `issue()` but resolves the key by its primary-key id. Preferred
     * when the caller already holds the appKeyId stamped by AppKeyRequiredFilter,
     * since it avoids an extra hash computation.
     */
    public function issueByKeyId(int $appKeyId): ServiceTokenResponseDTO;
}
