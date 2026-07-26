<?php

declare(strict_types=1);

namespace Tests\Integration\Traits;

use App\Models\UserModel;
use Tests\Support\IntegrationTestCase;

/**
 * Searchable Trait Tests
 */
class SearchableTest extends IntegrationTestCase
{
    private UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('SEARCH_ENABLED=true');
        putenv('SEARCH_MIN_LENGTH=3');
        putenv('SEARCH_USE_FULLTEXT=false');  // Force LIKE for testing

        $this->model = new UserModel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('SEARCH_ENABLED');
        putenv('SEARCH_MIN_LENGTH');
        putenv('SEARCH_USE_FULLTEXT');
    }

    public function testSearchAddsLikeConditions(): void
    {
        $this->model->search('john');  // 4 characters, meets minimum of 3

        $sql = $this->model->builder()->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('%john%', $sql);
    }

    public function testSearchReturnsModelInstance(): void
    {
        $result = $this->model->search('test');

        $this->assertSame($this->model, $result);
    }

    public function testSearchCanBeChained(): void
    {
        $result = $this->model
            ->search('john')
            ->where('status', 'active')
            ->limit(10);

        $sql = $result->builder()->getCompiledSelect();

        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testSearchDoesNothingWhenQueryEmpty(): void
    {
        $sql1 = $this->model->builder()->getCompiledSelect(false);

        $this->model->search('');

        $sql2 = $this->model->builder()->getCompiledSelect(false);

        $this->assertEquals($sql1, $sql2);
    }

    public function testSearchSearchesMultipleFields(): void
    {
        $this->model->search('test');

        $sql = $this->model->builder()->getCompiledSelect();

        // Should search in email, first_name, and last_name
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testGetSearchableFieldsReturnsArray(): void
    {
        $fields = $this->model->getSearchableFields();

        $this->assertIsArray($fields);
        $this->assertContains('email', $fields);
        $this->assertContains('first_name', $fields);
        $this->assertContains('last_name', $fields);
    }

    public function testIsSearchableReturnsTrueForAllowedField(): void
    {
        $this->assertTrue($this->model->isSearchable('email'));
        $this->assertTrue($this->model->isSearchable('first_name'));
    }

    public function testIsSearchableReturnsFalseForDisallowedField(): void
    {
        $this->assertFalse($this->model->isSearchable('password'));
        $this->assertFalse($this->model->isSearchable('unknown_field'));
    }

    public function testSearchWithEmptyQueryDoesNothing(): void
    {
        $sql1 = $this->model->builder()->getCompiledSelect(false);

        $this->model->search('');

        $sql2 = $this->model->builder()->getCompiledSelect(false);

        // Already tested above, but keeping for clarity
        $this->assertEquals($sql1, $sql2);
    }

    public function testUseFulltextSearchReturnsFalseForNonMySQLDriver(): void
    {
        // The trait checks DBDriver property
        // In test environment with MySQLi, this will return true
        // Testing the false case would require mocking the database driver
        $this->markTestSkipped('Requires database driver mocking');
    }

    public function testSearchRespectsMinimumLength(): void
    {
        putenv('SEARCH_MIN_LENGTH=5');

        $sql1 = $this->model->builder()->getCompiledSelect(false);

        $this->model->search('test');

        $sql2 = $this->model->builder()->getCompiledSelect(false);

        // Query should not change (search term too short)
        $this->assertEquals($sql1, $sql2);
    }
}
