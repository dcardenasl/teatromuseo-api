<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use CodeIgniter\Model;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Filters\QueryBuilder;

class QueryBuilderTest extends CIUnitTestCase
{
    /**
     * Create a mock model with filterableFields and tracking of applied filters.
     */
    protected function createMockModel(array $filterableFields = []): object
    {
        return new class ($filterableFields) extends Model {
            protected $table = 'test_table';
            public array $filterableFields;
            public array $appliedFilters = [];

            public function __construct(array $filterableFields)
            {
                $this->filterableFields = $filterableFields;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['where', $key, $value];
                return $this;
            }

            public function like($field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false): static
            {
                $this->appliedFilters[] = ['like', $field, $match];
                return $this;
            }

            public function whereIn(?string $key = null, $values = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['whereIn', $key, $values];
                return $this;
            }

            public function whereNotIn(?string $key = null, $values = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['whereNotIn', $key, $values];
                return $this;
            }
        };
    }

    /**
     * Create a mock model without filterableFields property.
     */
    protected function createModelWithoutFilterableFields(): object
    {
        return new class () extends Model {
            protected $table = 'test_table';
            public array $appliedFilters = [];

            public function __construct()
            {
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['where', $key, $value];
                return $this;
            }

            public function like($field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false): static
            {
                $this->appliedFilters[] = ['like', $field, $match];
                return $this;
            }

            public function whereIn(?string $key = null, $values = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['whereIn', $key, $values];
                return $this;
            }

            public function whereNotIn(?string $key = null, $values = null, ?bool $escape = null): static
            {
                $this->appliedFilters[] = ['whereNotIn', $key, $values];
                return $this;
            }
        };
    }

    public function testFilterWithAllowedFieldAppliesFilter(): void
    {
        $model = $this->createMockModel(['role', 'status', 'email']);
        $builder = new QueryBuilder($model);

        $builder->filter(['role' => 'admin']);

        $this->assertCount(1, $model->appliedFilters);
        $this->assertEquals(['where', 'role', 'admin'], $model->appliedFilters[0]);
    }

    public function testFilterWithDisallowedFieldIsIgnored(): void
    {
        $model = $this->createMockModel(['role', 'status', 'email']);
        $builder = new QueryBuilder($model);

        $builder->filter(['password' => ['like' => '$2y$']]);

        $this->assertCount(0, $model->appliedFilters);
    }

    public function testFilterWithSensitiveTokenFieldIsIgnored(): void
    {
        $model = $this->createMockModel(['role', 'status', 'email']);
        $builder = new QueryBuilder($model);

        $builder->filter(['email_verification_token' => ['like' => 'abc']]);

        $this->assertCount(0, $model->appliedFilters);
    }

    public function testFilterMixedFieldsOnlyAppliesAllowed(): void
    {
        $model = $this->createMockModel(['status', 'email']);
        $builder = new QueryBuilder($model);

        $builder->filter([
            'status'                   => 'active',
            'password'                 => ['like' => '$2y$'],
            'email'                    => ['like' => 'test'],
            'email_verification_token' => ['eq' => 'secret'],
        ]);

        $this->assertCount(2, $model->appliedFilters);
        $this->assertEquals(['where', 'status', 'active'], $model->appliedFilters[0]);
        $this->assertEquals(['like', 'email', 'test'], $model->appliedFilters[1]);
    }

    public function testFilterWithoutFilterableFieldsAllowsAll(): void
    {
        $model = $this->createModelWithoutFilterableFields();
        $builder = new QueryBuilder($model);

        $builder->filter([
            'any_field'    => 'value1',
            'another_field' => 'value2',
        ]);

        $this->assertCount(2, $model->appliedFilters);
    }

    public function testFilterWithEmptyFilterableFieldsAllowsAll(): void
    {
        $model = $this->createMockModel([]);
        $builder = new QueryBuilder($model);

        $builder->filter([
            'any_field'    => 'value1',
            'another_field' => 'value2',
        ]);

        $this->assertCount(2, $model->appliedFilters);
    }

    public function testFilterWithOperatorsOnAllowedField(): void
    {
        $model = $this->createMockModel(['created_at', 'status']);
        $builder = new QueryBuilder($model);

        $builder->filter(['created_at' => ['gte' => '2024-01-01']]);

        $this->assertCount(1, $model->appliedFilters);
        $this->assertEquals(['where', 'created_at >=', '2024-01-01'], $model->appliedFilters[0]);
    }
}
