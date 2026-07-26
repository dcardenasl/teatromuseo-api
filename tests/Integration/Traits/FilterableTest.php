<?php

declare(strict_types=1);

namespace Tests\Integration\Traits;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use Tests\Support\IntegrationTestCase;

/**
 * Filterable Trait Tests
 */
class FilterableTest extends IntegrationTestCase
{
    private object $model;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous model class that uses the trait
        $this->model = new class () extends Model {
            use Filterable;

            protected $table            = 'users';
            protected $returnType       = 'array';
            protected $allowedFields    = ['email', 'first_name', 'last_name', 'role', 'status'];
            protected array $filterableFields = ['email', 'role', 'status', 'created_at'];
        };
    }

    public function testApplyFiltersFiltersDisallowedFields(): void
    {
        $filters = [
            'email' => 'test@example.com',
            'role' => 'admin',
            'password' => 'secret',  // Not in filterableFields
        ];

        $this->model->applyFilters($filters);

        $sql = $this->model->builder()->getCompiledSelect();

        // Should include allowed fields
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('role', $sql);

        // Should NOT include disallowed field
        $this->assertStringNotContainsString('password', $sql);
    }

    public function testApplyFiltersHandlesSimpleEquality(): void
    {
        $this->model->applyFilters(['role' => 'admin']);

        $sql = $this->model->builder()->getCompiledSelect();

        $this->assertStringContainsString('role', $sql);
        $this->assertStringContainsString('admin', $sql);
    }

    public function testApplyFiltersHandlesOperators(): void
    {
        $filters = [
            'status' => ['ne' => 'deleted'],
        ];

        $this->model->applyFilters($filters);

        $sql = $this->model->builder()->getCompiledSelect();

        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('!=', $sql);
    }

    public function testApplyFiltersHandlesMultipleFilters(): void
    {
        $filters = [
            'role' => 'admin',
            'status' => 'active',
        ];

        $this->model->applyFilters($filters);

        $sql = $this->model->builder()->getCompiledSelect();

        $this->assertStringContainsString('role', $sql);
        $this->assertStringContainsString('admin', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('active', $sql);
    }

    public function testApplyFiltersReturnsModelInstance(): void
    {
        $result = $this->model->applyFilters(['role' => 'admin']);

        $this->assertSame($this->model, $result);
    }

    public function testGetFilterableFieldsReturnsArray(): void
    {
        $fields = $this->model->getFilterableFields();

        $this->assertIsArray($fields);
        $this->assertContains('email', $fields);
        $this->assertContains('role', $fields);
        $this->assertContains('status', $fields);
    }

    public function testIsFilterableReturnsTrueForAllowedField(): void
    {
        $this->assertTrue($this->model->isFilterable('email'));
        $this->assertTrue($this->model->isFilterable('role'));
    }

    public function testIsFilterableReturnsFalseForDisallowedField(): void
    {
        $this->assertFalse($this->model->isFilterable('password'));
        $this->assertFalse($this->model->isFilterable('unknown_field'));
    }

    public function testApplyFiltersCanBeChained(): void
    {
        $result = $this->model
            ->applyFilters(['role' => 'admin'])
            ->where('status', 'active')
            ->limit(10);

        $sql = $result->builder()->getCompiledSelect();

        $this->assertStringContainsString('role', $sql);
        $this->assertStringContainsString('admin', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }
}
