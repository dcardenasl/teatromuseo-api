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

    public function testFindIdByCodeReturnsIdWhenFoundAndNullOtherwise(): void
    {
        $model = new RoleModel();
        $code = 'role-code-' . uniqid('', true);
        $id = (int) $model->insert(['code' => $code, 'name' => 'Some Role']);

        $this->assertSame($id, $model->findIdByCode($code));
        $this->assertNull($model->findIdByCode('nonexistent-code-' . uniqid('', true)));
    }

    public function testFindCodesByIdsReturnsMapKeyedById(): void
    {
        $model = new RoleModel();
        $codeA = 'role-a-' . uniqid('', true);
        $codeB = 'role-b-' . uniqid('', true);
        $idA = (int) $model->insert(['code' => $codeA, 'name' => 'A']);
        $idB = (int) $model->insert(['code' => $codeB, 'name' => 'B']);

        $codes = $model->findCodesByIds([$idA, $idB]);

        $this->assertSame($codeA, $codes[$idA]);
        $this->assertSame($codeB, $codes[$idB]);
    }

    public function testFindCodesByIdsWithEmptyArrayReturnsEmpty(): void
    {
        $model = new RoleModel();

        $this->assertSame([], $model->findCodesByIds([]));
    }

    public function testExistsByIdReturnsTrueAndFalseCorrectly(): void
    {
        $model = new RoleModel();
        $id = (int) $model->insert(['code' => 'exists-role-' . uniqid('', true), 'name' => 'Exists']);

        $this->assertTrue($model->existsById($id));
        $this->assertFalse($model->existsById(999999999));
    }

    public function testIsSystemRoleReflectsFlag(): void
    {
        $model = new RoleModel();
        $systemId = (int) $model->insert(['code' => 'sys-' . uniqid('', true), 'name' => 'Sys', 'is_system' => 1]);
        $normalId = (int) $model->insert(['code' => 'normal-' . uniqid('', true), 'name' => 'Normal', 'is_system' => 0]);

        $this->assertTrue($model->isSystemRole($systemId));
        $this->assertFalse($model->isSystemRole($normalId));
    }

    public function testListAllOrderedByNameIncludesInsertedRole(): void
    {
        $model = new RoleModel();
        $code = 'ordered-name-' . uniqid('', true);
        $model->insert(['code' => $code, 'name' => 'Zzz Ordered Role']);

        $rows = $model->listAllOrderedByName();

        $found = array_filter($rows, static fn (array $r): bool => $r['code'] === $code);
        $this->assertNotEmpty($found);
        $row = array_values($found)[0];
        $this->assertSame('Zzz Ordered Role', $row['name']);
        $this->assertArrayHasKey('is_self_assignable', $row);
    }

    public function testListAllOrderedByCodeIncludesInsertedRole(): void
    {
        $model = new RoleModel();
        $code = 'ordered-code-' . uniqid('', true);
        $model->insert(['code' => $code, 'name' => 'Some Name']);

        $rows = $model->listAllOrderedByCode();

        $found = array_filter($rows, static fn (array $r): bool => $r['code'] === $code);
        $this->assertNotEmpty($found);
    }
}
