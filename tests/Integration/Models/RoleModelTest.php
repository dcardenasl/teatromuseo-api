<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RoleModel;
use Tests\Support\IntegrationTestCase;

/**
 * Smoke tests for RoleModel. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class RoleModelTest extends IntegrationTestCase
{
    public function testModelReportsCorrectTable(): void
    {
        $model = new RoleModel();

        $this->assertSame('roles', $model->getTable());
    }
}
