<?php

declare(strict_types=1);

namespace App\Filters\Concerns;

use App\Entities\ApiKeyEntity;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

trait ApiKeyThrottleHelpers
{
    /**
     * Rate limit cache TTL for resolved API keys (5 minutes).
     */
    private const API_KEY_CACHE_TTL = 300;

    /**
     * Resolve an API key entity from the raw key value.
     *
     * Results are cached for API_KEY_CACHE_TTL seconds.
     * Cache misses (invalid keys) are NOT cached.
     *
     * @return ApiKeyEntity|false ApiKeyEntity on success, false when invalid/inactive
     */
    private function resolveApiKey(CacheInterface $cache, string $rawKey): ApiKeyEntity|false
    {
        $hash     = hash('sha256', $rawKey);
        $cacheKey = 'api_key_' . $hash;

        $cached = $cache->get($cacheKey);

        if ($cached !== null) {
            $entity = new ApiKeyEntity();
            foreach ($cached as $field => $value) {
                $entity->$field = $value;
            }

            return $entity;
        }

        $model  = Services::apiKeyModel();
        $apiKey = $model->findByHash($hash);

        if (!$apiKey || !$apiKey->isActive()) {
            return false;
        }

        $cache->save($cacheKey, $apiKey->toArray(), self::API_KEY_CACHE_TTL);

        return $apiKey;
    }

    /**
     * Best-effort extraction of user ID from Authorization Bearer token.
     */
    private function extractUserIdFromBearer(RequestInterface $request): ?int
    {
        $authHeader = $request->getHeaderLine('Authorization');

        $token = Services::bearerTokenService()->extractFromHeader($authHeader);

        if ($token === null) {
            return null;
        }

        try {
            $jwtService = Services::jwtService();
            $decoded    = $jwtService->decode($token);

            return ($decoded && isset($decoded->uid)) ? (int) $decoded->uid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check and increment rate limit counter for a cache key.
     *
     * @return int|false Remaining requests if allowed, false if limit exceeded
     */
    private function checkRateLimit(CacheInterface $cache, string $key, int $limit, int $window): int|false
    {
        $requests = $cache->get($key);

        if ($requests === null) {
            $cache->save($key, 1, $window);

            return $limit - 1;
        }

        $requests = (int) $requests;

        if ($requests >= $limit) {
            return false;
        }

        // Use increment() to preserve the original TTL (fixed window, not sliding).
        // save() would reset the TTL on every request, making the window never expire.
        $cache->increment($key);

        return $limit - ($requests + 1);
    }

    private function unauthorizedApiKeyResponse(ResponseInterface $response): ResponseInterface
    {
        $body = ApiResponse::error(
            ['api_key' => lang('ApiKeys.invalidKey')],
            lang('ApiKeys.unauthorized'),
            401
        );

        $response->setStatusCode(401);
        $response->setJSON($body);

        return $response;
    }

    /**
     * Shared API key throttling strategy for both general and auth-specific filters.
     *
     * @param callable(int,int):ResponseInterface $onLimitExceeded
     * @return array{limit:int,remaining:int,reset:int}|ResponseInterface
     */
    private function enforceApiKeyRateLimit(
        CacheInterface $cache,
        ApiKeyEntity $appKey,
        string $ip,
        ?int $userId,
        int $defaultWindow,
        callable $onLimitExceeded
    ): array|ResponseInterface {
        $appKeyWindow = $appKey->rate_limit_window > 0
            ? $appKey->rate_limit_window
            : $defaultWindow;

        $keyBucket    = 'api_key_' . $appKey->id;
        $keyRemaining = $this->checkRateLimit(
            $cache,
            $keyBucket,
            $appKey->rate_limit_requests,
            $appKeyWindow
        );

        if ($keyRemaining === false) {
            $this->logApiKeyRateLimitExceeded($appKey, $ip, $userId, 'api_key');
            return $onLimitExceeded($appKey->rate_limit_requests, $appKeyWindow);
        }

        if ($userId !== null) {
            $userBucket    = 'api_key_' . $appKey->id . '_user_' . $userId;
            $userRemaining = $this->checkRateLimit(
                $cache,
                $userBucket,
                $appKey->user_rate_limit,
                $appKeyWindow
            );

            if ($userRemaining === false) {
                $this->logApiKeyRateLimitExceeded($appKey, $ip, $userId, 'user');
                return $onLimitExceeded($appKey->user_rate_limit, $appKeyWindow);
            }
        } else {
            $ipBucket    = 'api_key_' . $appKey->id . '_ip_' . md5($ip);
            $ipRemaining = $this->checkRateLimit(
                $cache,
                $ipBucket,
                $appKey->ip_rate_limit,
                $appKeyWindow
            );

            if ($ipRemaining === false) {
                $this->logApiKeyRateLimitExceeded($appKey, $ip, null, 'ip');
                return $onLimitExceeded($appKey->ip_rate_limit, $appKeyWindow);
            }
        }

        return [
            'limit' => $appKey->rate_limit_requests,
            'remaining' => max(0, $keyRemaining),
            'reset' => time() + $appKeyWindow,
        ];
    }

    private function logApiKeyAuthFailure(string $rawKey, RequestInterface $request): void
    {
        Services::securityAuditLogger()->logApiKeyAuthFailure($rawKey, $request);
    }

    private function logApiKeyRateLimitExceeded(ApiKeyEntity $appKey, string $ip, ?int $userId, string $scope): void
    {
        Services::securityAuditLogger()->logApiKeyRateLimitExceeded($appKey, $ip, $userId, $scope);
    }
}
