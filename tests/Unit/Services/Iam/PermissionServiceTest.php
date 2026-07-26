<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\Interfaces\Iam\PermissionServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Smoke tests for PermissionService. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class PermissionServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        $service = Services::permissionService(false);

        $this->assertInstanceOf(PermissionServiceInterface::class, $service);
    }
}
