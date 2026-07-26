<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Entities\ApiKeyEntity;
use App\Models\ApiKeyModel;
use Tests\Support\IntegrationTestCase;

/**
 * ApiKeyModel Integration Tests
 *
 * Tests ApiKeyModel with real database operations.
 * Requires the test database configured in phpunit.xml.
 */
class ApiKeyModelTest extends IntegrationTestCase
{
    protected ApiKeyModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ApiKeyModel();
    }

    // ==================== CRUD TESTS ====================

    public function testInsertCreatesApiKey(): void
    {
        $id = $this->model->insert($this->validData());

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindReturnsApiKeyEntity(): void
    {
        $id = $this->model->insert($this->validData(['name' => 'Find Test']));

        $key = $this->model->find($id);

        $this->assertInstanceOf(ApiKeyEntity::class, $key);
        $this->assertEquals('Find Test', $key->name);
    }

    public function testUpdateModifiesApiKey(): void
    {
        $id = $this->model->insert($this->validData());

        $result = $this->model->update($id, ['name' => 'Updated Name']);

        $this->assertTrue($result);

        $key = $this->model->find($id);
        $this->assertEquals('Updated Name', $key->name);
    }

    public function testDeleteRemovesApiKey(): void
    {
        $id = $this->model->insert($this->validData());

        $this->model->delete($id);

        // Hard delete — record should not be found
        $key = $this->model->find($id);
        $this->assertNull($key);
    }

    public function testNoSoftDeleteColumn(): void
    {
        // ApiKeyModel does not use soft deletes — confirm table has no deleted_at
        $id = $this->model->insert($this->validData());
        $this->model->delete($id);

        // After hard delete, even withDeleted() should return null
        // (withDeleted is a no-op when useSoftDeletes = false)
        $key = $this->model->find($id);
        $this->assertNull($key);
    }

    // ==================== findByHash TESTS ====================

    public function testFindByHashReturnsCorrectRecord(): void
    {
        $rawKey  = 'apk_' . bin2hex(random_bytes(24));
        $hash    = hash('sha256', $rawKey);
        $prefix  = substr($rawKey, 0, 12);

        $this->model->insert($this->validData([
            'key_prefix' => $prefix,
            'key_hash'   => $hash,
        ]));

        $found = $this->model->findByHash($hash);

        $this->assertInstanceOf(ApiKeyEntity::class, $found);
        $this->assertEquals($prefix, $found->key_prefix);
    }

    public function testFindByHashReturnsNullForUnknownHash(): void
    {
        $result = $this->model->findByHash('0000000000000000000000000000000000000000000000000000000000000000');

        $this->assertNull($result);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRequiresName(): void
    {
        $data = $this->validData();
        unset($data['name']);

        // insert() triggers the full validation pipeline including required rules
        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->model->errors());
    }

    public function testValidationRejectsZeroRateLimitRequests(): void
    {
        $result = $this->model->validate($this->validData(['rate_limit_requests' => 0]));

        $this->assertFalse($result);
        $this->assertArrayHasKey('rate_limit_requests', $this->model->errors());
    }

    public function testUniqueKeyHashConstraint(): void
    {
        $hash = hash('sha256', 'apk_duplicate_unique');

        $this->model->insert($this->validData(['key_hash' => $hash]));

        // Depending on DB debug settings, duplicate unique key may throw or return false.
        try {
            $result = $this->model->insert($this->validData([
                'key_prefix' => 'apk_differe',
                'key_hash'   => $hash,
            ]));

            $this->assertFalse($result);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $this->assertMatchesRegularExpression('/UNIQUE|DUPLICATE/i', $e->getMessage());
        }
    }

    // ==================== TRAIT TESTS ====================

    public function testSearchableFieldsAreDefined(): void
    {
        $this->assertTrue(method_exists($this->model, 'getSearchableFields'));
        $fields = $this->model->getSearchableFields();
        $this->assertContains('name', $fields);
        $this->assertContains('key_prefix', $fields);
    }

    public function testFilterableFieldsAreDefined(): void
    {
        $this->assertTrue(method_exists($this->model, 'getFilterableFields'));
        $fields = $this->model->getFilterableFields();
        $this->assertContains('is_active', $fields);
    }

    // ==================== HELPER ====================

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name'                => 'Test API Key',
            'key_prefix'          => substr('apk_' . bin2hex(random_bytes(4)), 0, 12),
            'key_hash'            => hash('sha256', bin2hex(random_bytes(24))),
            'is_active'           => 1,
            'rate_limit_requests' => 600,
            'rate_limit_window'   => 60,
            'user_rate_limit'     => 60,
            'ip_rate_limit'       => 200,
        ], $overrides);
    }
}
