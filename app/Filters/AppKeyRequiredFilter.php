<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Concerns\ApiKeyThrottleHelpers;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * Requires a valid X-App-Key header to access the route.
 *
 * Used by endpoints that authenticate the calling application (not a user),
 * such as token introspection. Returns 401 when the header is missing
 * (no credential provided) and 403 when present but invalid/inactive
 * (credential rejected) — matching RFC 7235 semantics.
 */
class AppKeyRequiredFilter implements FilterInterface
{
    use ApiKeyThrottleHelpers;

    public function before(RequestInterface $request, $arguments = null)
    {
        $rawKey = $request->getHeaderLine('X-App-Key');

        if ($rawKey === '') {
            return $this->errorResponse(401, lang('Auth.appKeyMissing'));
        }

        $cache  = Services::cache();
        $appKey = $this->resolveApiKey($cache, $rawKey);

        if ($appKey === false) {
            $this->logApiKeyAuthFailure($rawKey, $request);

            return $this->errorResponse(403, lang('Auth.appKeyInvalid'));
        }

        if ($request instanceof ApiRequest) {
            $request->setAppKeyId($appKey->id);
            $request->setAppId($appKey->application_id);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $body = $status === 401
            ? ApiResponse::unauthorized($message)
            : ApiResponse::forbidden($message);

        return Services::response()
            ->setStatusCode($status)
            ->setJSON($body);
    }
}
