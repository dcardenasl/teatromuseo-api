<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * Test Auth Filter
 *
 * Minimal filter for testing that trusts the ContextHolder.
 * Used to avoid JWT complexities during integration tests.
 */
class TestAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT !== 'testing') {
            return $request;
        }

        $context = \dcardenasl\Ci4ApiCore\Http\ContextHolder::get();

        // If test established context, populate request and allow
        if ($context !== null && $context->user_id !== null) {
            $permissions = $context->permissions;
            // Legacy compat: tests that still set X-Test-User-Role / pass role in actAs
            // can ride along with empty permissions; the header below resolves them.
            $roleHint = $request->getHeaderLine('X-Test-User-Role');
            if ($permissions === [] && $roleHint !== '') {
                $permissions = \App\Support\TestPermissionResolver::permissionsForRole($roleHint);
                if ($permissions !== []) {
                    \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(
                        $context->user_id,
                        $context->metadata,
                        $permissions
                    ));
                }
            }
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $context->user_id, $permissions);
            }
            return $request;
        }

        // Otherwise, fall back to standard headers for tests that don't use actAs correctly
        $testUserId = $request->getHeaderLine('X-Test-User-Id');
        if ($testUserId !== '') {
            $testUserRole = $request->getHeaderLine('X-Test-User-Role') ?: 'user';
            $permissions = \App\Support\TestPermissionResolver::permissionsForRole($testUserRole);
            \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext(
                (int) $testUserId,
                [],
                $permissions
            ));
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $testUserId, $permissions);
            }
            return $request;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader !== '') {
            return (new JwtAuthFilter())->before($request, $arguments);
        }

        // If no identity found, return 401
        return Services::response()
            ->setJSON(ApiResponse::unauthorized(lang('Auth.authRequired')))
            ->setStatusCode(401);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
