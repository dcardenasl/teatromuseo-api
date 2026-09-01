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
    protected $seed = \App\Database\Seeds\RbacBootstrapSeeder::class;

    public function testModelReportsCorrectTable(): void
    {
        $model = new PermissionModel();

        $this->assertSame('permissions', $model->getTable());
    }

    private function insertPermission(string $code, int $applicationId = 1): int
    {
        return (int) (new PermissionModel())->insert([
            'application_id' => $applicationId,
            'code' => $code,
            'resource' => 'test',
            'action' => 'do',
        ]);
    }

    public function testFindIdsByCodesReturnsMatchingIds(): void
    {
        $model = new PermissionModel();
        $codeA = 'pm.a.' . uniqid('', true);
        $codeB = 'pm.b.' . uniqid('', true);
        $idA = $this->insertPermission($codeA);
        $idB = $this->insertPermission($codeB);

        $ids = $model->findIdsByCodes([$codeA, $codeB]);

        sort($ids);
        $expected = [$idA, $idB];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testFindIdsByCodesWithEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], (new PermissionModel())->findIdsByCodes([]));
    }

    public function testFindExistingIdsFiltersOutMissingIds(): void
    {
        $model = new PermissionModel();
        $id = $this->insertPermission('pm.existing.' . uniqid('', true));

        $existing = $model->findExistingIds([$id, 999999999]);

        $this->assertSame([$id], $existing);
    }

    public function testFindExistingIdsWithEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], (new PermissionModel())->findExistingIds([]));
    }

    public function testFindCodesByIdsReturnsDeduplicatedCodes(): void
    {
        $model = new PermissionModel();
        $code = 'pm.codes.' . uniqid('', true);
        $id = $this->insertPermission($code);

        $this->assertSame([$code], $model->findCodesByIds([$id]));
    }

    public function testFindCodesByApplicationFiltersByApplicationId(): void
    {
        $model = new PermissionModel();
        $code = 'pm.byapp.' . uniqid('', true);
        $this->insertPermission($code, 1);

        $codes = $model->findCodesByApplication(1);
        $this->assertContains($code, $codes);

        $codesForOtherApp = $model->findCodesByApplication(999999);
        $this->assertNotContains($code, $codesForOtherApp);
    }

    public function testFindAllCodesIncludesInsertedPermission(): void
    {
        $model = new PermissionModel();
        $code = 'pm.all.' . uniqid('', true);
        $this->insertPermission($code);

        $this->assertContains($code, $model->findAllCodes());
    }

    public function testGroupedByApplicationGroupsPermissionsByApplicationId(): void
    {
        $model = new PermissionModel();
        $code = 'pm.grouped.' . uniqid('', true);
        $this->insertPermission($code, 1);

        $grouped = $model->groupedByApplication();

        $this->assertArrayHasKey(1, $grouped);
        $codes = array_column($grouped[1], 'code');
        $this->assertContains($code, $codes);
    }
}
