<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\Interfaces\Iam\RoleServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Smoke tests for RoleService. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class RoleServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        $service = Services::roleService(false);

        $this->assertInstanceOf(RoleServiceInterface::class, $service);
    }
}
