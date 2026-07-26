<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Concerns\ApiKeyThrottleHelpers;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\Filters\Concerns\RateLimitResponseHelpers;

/**
 * Authentication-specific Rate Limiting Filter
 *
 * Applies stricter rate limits to authentication endpoints (login, register,
 * password reset) to prevent brute-force attacks and credential stuffing.
 */
class AuthThrottleFilter implements FilterInterface
{
    use ApiKeyThrottleHelpers;
    use RateLimitResponseHelpers;

    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();
        $response = Services::response();

        $apiConfig = config('Api');
        $maxAttempts = $apiConfig->authRateLimitRequests;
        $window = $apiConfig->authRateLimitWindow;
        $path = $this->resolvePath($request);
        [$maxAttempts, $window] = $this->applyRouteOverrides($path, $maxAttempts, $window);
        $ip = $request->getIPAddress();
        $user_id = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        // API key policy for auth routes:
        // 1) If X-App-Key is present, validate it first and enforce key-based limits.
        // 2) If X-App-Key is absent, fallback to auth IP-based throttle.
        $rawKey = $request->getHeaderLine('X-App-Key');
        if ($rawKey !== '') {
            $appKey = $this->resolveApiKey($cache, $rawKey);

            if ($appKey === false) {
                $this->logApiKeyAuthFailure($rawKey, $request);
                return $this->unauthorizedApiKeyResponse($response);
            }

            if ($user_id === null) {
                $user_id = $this->extractUserIdFromBearer($request);
            }

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

            if ($request instanceof ApiRequest) {
                $request->setAuthRateLimitInfo($apiKeyResult);
                $request->setAppKeyId($appKey->id);
            }

            return $request;
        }

        // No API key: use stricter auth route limit by IP.
        $cacheKey = $this->resolveCacheKey($ip, $path);

        $remaining = $this->checkRateLimit($cache, $cacheKey, $maxAttempts, $window);

        if ($remaining === false) {
            return $this->rateLimitExceeded($response, $maxAttempts, $window);
        }

        if ($request instanceof ApiRequest) {
            $request->setAuthRateLimitInfo([
                'limit' => $maxAttempts,
                'remaining' => max(0, $remaining),
                'reset' => time() + $window,
            ]);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof ApiRequest && $request->getAuthRateLimitInfo() !== null) {
            $info = $request->getAuthRateLimitInfo();
            $this->attachRateLimitHeaders($response, $info);
        }

        return $response;
    }

    private function rateLimitExceeded(ResponseInterface $response, int $maxAttempts, int $window): ResponseInterface
    {
        return $this->buildRateLimitExceededResponse(
            $response,
            $maxAttempts,
            $window,
            'Auth.tooManyLoginAttempts',
            [$maxAttempts, (int) ($window / 60)]
        );
    }

    private function resolveCacheKey(string $ip, string $path): string
    {
        return 'auth_rate_limit_' . md5($ip . '|' . ltrim($path, '/'));
    }

    private function resolvePath(RequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array{int,int}
     */
    private function applyRouteOverrides(string $path, int $maxAttempts, int $window): array
    {
        // auth/refresh is routine session-renewal traffic (requires a valid
        // refresh token already, not a guessable credential), so it doesn't
        // need the brute-force-strength limit applied to login/register/etc.
        if (ltrim($path, '/') === 'auth/refresh') {
            return [30, $window];
        }

        return [$maxAttempts, $window];
    }
}
