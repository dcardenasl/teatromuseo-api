<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class RateLimitResponseTest extends ApiTestCase
{
    use AuthTestTrait;

    protected function tearDown(): void
    {
        $this->clearEnvVar('AUTH_RATE_LIMIT_REQUESTS');
        $this->clearEnvVar('AUTH_RATE_LIMIT_WINDOW');
        $this->clearEnvVar('RATE_LIMIT_REQUESTS');
        $this->clearEnvVar('RATE_LIMIT_USER_REQUESTS');
        $this->clearEnvVar('RATE_LIMIT_WINDOW');
        parent::tearDown();
    }

    public function testAuthThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->setEnvVar('AUTH_RATE_LIMIT_REQUESTS', '1');
        $this->setEnvVar('AUTH_RATE_LIMIT_WINDOW', '60');

        $email = 'ratelimit_auth_' . uniqid() . '@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user', 'active', true);

        $first = $this->withBodyFormat('json')->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $first->assertStatus(200);

        $second = $this->withBodyFormat('json')->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $second->assertStatus(429);
        $this->assertCanonicalRateLimitResponse($second);
    }

    public function testGeneralThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->setEnvVar('RATE_LIMIT_REQUESTS', '1');
        $this->setEnvVar('RATE_LIMIT_USER_REQUESTS', '1');
        $this->setEnvVar('RATE_LIMIT_WINDOW', '60');

        $userId = $this->createUser('ratelimit_general_' . uniqid() . '@example.com', 'ValidPass123!', 'user', 'active', true);
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($userId));
        $this->setTestRequestHeaders([
            'X-Test-User-Id' => (string) $userId,
            'X-Test-User-Role' => 'user',
        ]);

        $first = $this->get("/api/v1/files?user_id={$userId}");
        $first->assertStatus(200);

        $this->resetRequest();

        $second = $this->get("/api/v1/files?user_id={$userId}");
        $second->assertStatus(429);
        $this->assertCanonicalRateLimitResponse($second);
    }

    private function setEnvVar(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function clearEnvVar(string $name): void
    {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    private function assertCanonicalRateLimitResponse($result): void
    {
        $json = $this->getResponseJson($result);

        $this->assertSame('error', $json['status'] ?? null);
        $this->assertSame(429, $json['code'] ?? null);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('rate_limit', $json['errors']);
        $this->assertArrayHasKey('retry_after', $json);

        $response = $result->response();
        $this->assertNotEmpty($response->getHeaderLine('Retry-After'));
        $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertMatchesRegularExpression('/^\d+$/', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertMatchesRegularExpression('/^\d+$/', $response->getHeaderLine('X-RateLimit-Reset'));
    }
}
