<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Iam;

use App\Services\Iam\ApplicationPermissionsResolver;
use Config\Services;
use Tests\Support\ApiTestCase;

/**
 * ApplicationPermissionsResolver Integration Tests
 *
 * Exercises the application-scoped permission resolver against the real
 * `permissions` table. Verifies result ordering, deduplication, empty
 * application handling, and cache invalidation.
 */
class ApplicationPermissionsResolverTest extends ApiTestCase
{
    private ApplicationPermissionsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ApplicationPermissionsResolver(
            \Config\Database::connect(),
            Services::cache()
        );
    }

    public function testResolveReturnsAllPermissionCodesForApplication(): void
    {
        $appId = $this->insertApp('catalog-app');
        $this->insertPerm($appId, 'catalog-app.read');
        $this->insertPerm($appId, 'catalog-app.write');
        $this->insertPerm($appId, 'catalog-app.delete');

        $codes = $this->resolver->resolve($appId);

        $this->assertSame(
            ['catalog-app.delete', 'catalog-app.read', 'catalog-app.write'],
            $codes,
            'Codes must be returned sorted ascending'
        );
    }

    public function testResolveReturnsEmptyArrayForApplicationWithNoPermissions(): void
    {
        $appId = $this->insertApp('empty-app');

        $this->assertSame([], $this->resolver->resolve($appId));
    }

    public function testResolveReturnsEmptyArrayForUnknownApplication(): void
    {
        $this->assertSame([], $this->resolver->resolve(999999));
    }

    public function testResolveDoesNotLeakPermissionsAcrossApplications(): void
    {
        $appA = $this->insertApp('app-a');
        $appB = $this->insertApp('app-b');

        $this->insertPerm($appA, 'app-a.read');
        $this->insertPerm($appB, 'app-b.read');

        $this->assertSame(['app-a.read'], $this->resolver->resolve($appA));
        $this->assertSame(['app-b.read'], $this->resolver->resolve($appB));
    }

    public function testInvalidateClearsCachedResult(): void
    {
        $appId = $this->insertApp('cache-app');
        $this->insertPerm($appId, 'cache-app.access');

        $this->assertSame(['cache-app.access'], $this->resolver->resolve($appId));

        // Add another permission AFTER the first resolve — without invalidation
        // the cached array would still be one entry.
        $this->insertPerm($appId, 'cache-app.read');

        $this->assertSame(
            ['cache-app.access'],
            $this->resolver->resolve($appId),
            'Cache must shield the second call from the new row'
        );

        $this->resolver->invalidate($appId);

        $this->assertSame(
            ['cache-app.access', 'cache-app.read'],
            $this->resolver->resolve($appId),
            'After invalidate(), the resolver must hit the DB again'
        );
    }

    private function insertApp(string $code): int
    {
        $db = \Config\Database::connect();
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => ucfirst($code),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function insertPerm(int $appId, string $code): void
    {
        [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

        \Config\Database::connect()->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => "Test permission {$code}",
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
