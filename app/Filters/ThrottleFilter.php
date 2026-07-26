<?php

declare(strict_types=1);

namespace App\Filters;

use App\Entities\ApiKeyEntity;
use App\Filters\Concerns\ApiKeyThrottleHelpers;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\Filters\Concerns\RateLimitResponseHelpers;

class ThrottleFilter implements FilterInterface
{
    use ApiKeyThrottleHelpers;
    use RateLimitResponseHelpers;

    /**
     * Rate limit requests by IP address, user ID (if authenticated), and API key
     * (if the X-App-Key header is present).
     *
     * Strategy matrix:
     *
     * | Context                | Primary limit         | Secondary limit         |
     * |------------------------|-----------------------|-------------------------|
     * | No key + no JWT        | IP (60/min)           | —                       |
     * | No key + with JWT      | IP (60/min)           | user_id (100/min)       |
     * | Valid key + no JWT     | app_key (600/min)     | IP defensive (200/min)  |
     * | Valid key + with JWT   | app_key (600/min)     | user_id (60/min)        |
     * | Invalid/inactive key   | 401 Unauthorized      | —                       |
     *
     * @param RequestInterface $request
     * @param array<int|string, mixed>|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache    = Services::cache();
        $response = Services::response();

        $ip     = $request->getIPAddress();
        $user_id = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        // ------------------------------------------------------------------
        // 1. Resolve API key from X-App-Key header
        // ------------------------------------------------------------------
        $rawKey = $request->getHeaderLine('X-App-Key');
        $appKey = null;

        if ($rawKey !== '') {
            $appKey = $this->resolveApiKey($cache, $rawKey);

            if ($appKey === false) {
                // Key present but invalid or inactive → 401
                $this->logApiKeyAuthFailure($rawKey, $request);
                return $this->unauthorizedApiKeyResponse($response);
            }
        }

        // ------------------------------------------------------------------
        // 2. Extract user_id from JWT when it has not yet been set by JwtAuthFilter
        //    (ThrottleFilter runs before JwtAuthFilter on public routes).
        //    We do a best-effort, non-security-critical parse of the bearer token
        //    solely to apply per-user rate limiting.
        // ------------------------------------------------------------------
        if ($user_id === null) {
            $user_id = $this->extractUserIdFromBearer($request);
        }

        // ------------------------------------------------------------------
        // 3. Apply rate limits according to strategy matrix
        // ------------------------------------------------------------------
        $apiConfig = config('Api');
        $window = $apiConfig->rateLimitWindow;

        if ($appKey instanceof ApiKeyEntity) {
            $apiKeyResult = $this->enforceApiKeyRateLimit(
                $cache,
                $appKey,
                $ip,
                $user_id,
                $window,
                fn (int $maxRequests, int $window): ResponseInterface =>
                    $this->rateLimitExceeded($response, $maxRequests, $window)
            );

            if ($apiKeyResult instanceof ResponseInterface) {
                return $apiKeyResult;
            }

            // Report the primary (key-level) limit in headers
            if ($request instanceof ApiRequest) {
                $request->setRateLimitInfo($apiKeyResult);
                $request->setAppKeyId($appKey->id);
            }
        } else {
            // ---- Fallback path (no API key) ----
            $ipLimit   = $apiConfig->rateLimitRequests;
            $userLimit = $apiConfig->rateLimitUserRequests;

            // IP-based rate limit (always applied)
            $ipKey       = 'rate_limit_ip_' . md5($ip);
            $ipRemaining = $this->checkRateLimit($cache, $ipKey, $ipLimit, $window);

            if ($ipRemaining === false) {
                return $this->rateLimitExceeded($response, $ipLimit, $window);
            }

            // User-based rate limit (only when authenticated)
            if ($user_id !== null) {
                $userKey       = 'rate_limit_user_' . $user_id;
                $userRemaining = $this->checkRateLimit($cache, $userKey, $userLimit, $window);

                if ($userRemaining === false) {
                    return $this->rateLimitExceeded($response, $userLimit, $window);
                }
            }

            // Store rate limit info in request for after() method (reports IP limit)
            if ($request instanceof ApiRequest) {
                $request->setRateLimitInfo([
                    'limit'     => $ipLimit,
                    'remaining' => max(0, $ipRemaining),
                    'reset'     => time() + $window,
                ]);
            }
        }

        return $request;
    }

    /**
     * Process after the request — attach rate limit response headers.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array<int|string, mixed>|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof ApiRequest && $request->getRateLimitInfo() !== null) {
            $info = $request->getRateLimitInfo();
            $this->attachRateLimitHeaders($response, $info);
        }

        return $response;
    }

    /**
     * Return 429 Too Many Requests response
     *
     * @param ResponseInterface $response
     * @param int               $maxRequests
     * @param int               $window
     * @return ResponseInterface
     */
    private function rateLimitExceeded(ResponseInterface $response, int $maxRequests, int $window): ResponseInterface
    {
        return $this->buildRateLimitExceededResponse(
            $response,
            $maxRequests,
            $window,
            'Auth.tooManyRequests',
            [$maxRequests, $window]
        );
    }
}
