<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\PermissionModel;
use Tests\Support\IntegrationTestCase;

/**
 * Smoke tests for PermissionModel. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class PermissionModelTest extends IntegrationTestCase
{
    public function testModelReportsCorrectTable(): void
    {
        $model = new PermissionModel();

        $this->assertSame('permissions', $model->getTable());
    }
}
