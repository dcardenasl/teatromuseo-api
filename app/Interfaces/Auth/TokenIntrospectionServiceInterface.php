<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\DTO\Response\Auth\IntrospectResponseDTO;

interface TokenIntrospectionServiceInterface
{
    /**
     * Validate a JWT and return its introspection result.
     *
     * Always returns a DTO — invalid/expired/revoked tokens are reported
     * via the `valid` flag rather than thrown as exceptions.
     *
     * When `$applicationId` is provided and the token carries a `uid` claim,
     * the returned `permissions` are re-resolved against that application so
     * each calling domain app receives only its own scope. Service tokens
     * (no `uid`) keep the scope baked into the JWT — it was already
     * application-bound at issue time.
     */
    public function introspect(IntrospectRequestDTO $request, ?int $applicationId = null): IntrospectResponseDTO;
}
