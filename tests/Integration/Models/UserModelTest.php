<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\UserModel;
use Tests\Support\IntegrationTestCase;

/**
 * UserModel Integration Tests
 *
 * Tests UserModel with real database operations.
 * Requires test database configured in phpunit.xml
 */
class UserModelTest extends IntegrationTestCase
{
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== BASIC CRUD TESTS ====================

    public function testInsertCreatesUser(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ];

        $userId = $this->userModel->insert($userData);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }

    public function testFindReturnsUserEntity(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'find@example.com',
            'first_name' => 'Find',
            'last_name' => 'Test',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $user = $this->userModel->find($userId);

        $this->assertInstanceOf(\App\Entities\UserEntity::class, $user);
        $this->assertEquals('find@example.com', $user->email);
    }

    public function testUpdateModifiesUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'update@example.com',
            'first_name' => 'Update',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $result = $this->userModel->update($userId, ['first_name' => 'Updated']);

        $this->assertTrue($result);

        $user = $this->userModel->find($userId);
        $this->assertEquals('Updated', $user->first_name);
    }

    public function testSoftDeleteRemovesFromResults(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'delete@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $this->userModel->delete($userId);

        $user = $this->userModel->find($userId);
        $this->assertNull($user);

        // But can be found with withDeleted
        $deletedUser = $this->userModel->withDeleted()->find($userId);
        $this->assertNotNull($deletedUser);
        $this->assertNotNull($deletedUser->deleted_at);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRejectsInvalidEmail(): void
    {
        $result = $this->userModel->validate([
            'email' => 'invalid-email',
            'password' => 'ValidPass123!',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    public function testValidationRejectsDuplicateEmail(): void
    {
        $this->userModel->insert([
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $result = $this->userModel->insert([
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    // ==================== TRAIT TESTS ====================

    public function testFilterableTraitFiltersResults(): void
    {
        $this->userModel->insert([
            'email' => 'active1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->userModel->insert([
            'email' => 'invited1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'status' => 'invited',
        ]);

        // Use applyFilters() method from Filterable trait
        $active = $this->userModel->applyFilters(['status' => ['eq' => 'active']])->findAll();

        $this->assertCount(1, $active);
        $this->assertEquals('active', $active[0]->status);
    }

    public function testSearchableTraitMethodExists(): void
    {
        // Verify Searchable trait methods are available
        $this->assertTrue(method_exists($this->userModel, 'search'));
        $this->assertTrue(method_exists($this->userModel, 'getSearchableFields'));

        // Verify searchable fields are defined
        $searchableFields = $this->userModel->getSearchableFields();
        $this->assertContains('email', $searchableFields);
        $this->assertContains('first_name', $searchableFields);
        $this->assertContains('last_name', $searchableFields);
    }
}
